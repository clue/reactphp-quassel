<?php

namespace Clue\Tests\React\Quassel;

use Clue\React\Block;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Models\BufferInfo;
use Clue\React\Quassel\Models\Message;
use React\EventLoop\Loop;
use React\Promise\Promise;

class FunctionalTest extends TestCase
{
    private static $host;
    private static $username;
    private static $password;

    private static $blocker;

    /**
     * @beforeClass
     */
    public static function setUpEnvironmentAndLoop()
    {
        if (!getenv('QUASSEL_HOST')) {
            return;
        }

        self::$host = getenv('QUASSEL_HOST');
        self::$username = getenv('QUASSEL_USER');
        if (!self::$username) {
            self::$username = 'quassel';
        }

        self::$password = getenv('QUASSEL_PASS');
        if (!self::$password) {
            self::$password = 'quassel';
        }
    }

    /**
     * @before
     */
    public function setUpSkipOnMissingEnvironment()
    {
        if (!self::$host) {
            $this->markTestSkipped('No ENV QUASSEL_HOST (plus optionally QUASSEL_USER and QUASSEL_PASS) given');
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateClient()
    {
        $factory = new Factory();
        $promise = $factory->createClient(self::$host);

        $client = Block\await($promise, Loop::get(), 10.0);

        return $client;
    }

    /**
     * @depends testCreateClient
     * @param Client $client
     */
    public function testWriteClientInit(Client $client)
    {
        $client->writeClientInit();

        $message = $this->awaitMessage($client);
        $this->assertEquals('ClientInitAck', $message->MsgType);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testWriteClientInit
     *
     * @param Client $client
     * @param \stdClass $message
     */
    public function testWriteCoreSetupData(Client $client, \stdClass $message)
    {
        if ($message->Configured) {
            $this->markTestSkipped('Given core already configured, can not set-up');
        }

        $client->writeCoreSetupData(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('CoreSetupAck', $message->MsgType);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testWriteClientInit
     *
     * @param Client $client
     * @param \stdClass $message
     */
    public function testWriteClientLogin(Client $client, \stdClass $message)
    {
        $client->writeClientLogin(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('ClientLoginAck', $message->MsgType);

        $message = $this->awaitMessage($client);
        $this->assertEquals('SessionInit', $message->MsgType);

        return $message;
    }

    /**
     * @depends testCreateClient
     *
     * @param Client $client
     */
    public function testWriteHeartBeat(Client $client)
    {
        // explicitly write a fixed time (current date) in order to preserve milliseconds (for PHP 7.1+)
        $time = new \DateTime('10:20:30.456+00:00');

        $promise = new Promise(function ($resolve) use ($client) {
            $callback = function ($message) use ($resolve, &$callback, $client) {
                if (is_array($message) && isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEATREPLY) {
                    $client->removeListener('data', $callback);
                    $resolve($message[1]);
                }
            };

            $client->on('data', $callback);
        });

        $client->writeHeartBeatRequest($time);

        $received = Block\await($promise, Loop::get(), 10.0);

        $this->assertEquals($time, $received);
    }

    /**
     * @depends testCreateClient
     *
     * @param Client $client
     */
    public function testWriteHeartBeatDefaultsToCurrentTime(Client $client)
    {
        $promise = new Promise(function ($resolve) use ($client) {
            $callback = function ($message) use ($resolve, &$callback, $client) {
                if (is_array($message) && isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEATREPLY) {
                    $client->removeListener('data', $callback);
                    $resolve($message[1]);
                }
            };

            $client->on('data', $callback);
        });

        $client->writeHeartBeatRequest();

        $received = Block\await($promise, Loop::get(), 10.0);

        $this->assertTrue($received instanceof \DateTime);
        $this->assertEqualsDelta(microtime(true), $received->getTimestamp(), 2.0);
    }

    /**
     * @depends testCreateClient
     * @doesNotPerformAssertions
     */
    public function testClose(Client $client)
    {
        $promise = new Promise(function ($resolve) use ($client) {
            $client->once('close', $resolve);
        });

        $client->close();

        return Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthUrlReceivesSessionInit()
    {
        $factory = new Factory();

        $url = rawurlencode(self::$username) . ':' . rawurlencode(self::$password) . '@' . self::$host;
        $promise = $factory->createClient($url);
        $client = Block\await($promise, Loop::get(), 10.0);

        $message = $this->awaitMessage($client);
        $this->assertEquals('SessionInit', $message->MsgType);

        $client->close();
    }

    public function testRequestBacklogReceivesBacklog()
    {
        $factory = new Factory();

        $url = rawurlencode(self::$username) . ':' . rawurlencode(self::$password) . '@' . self::$host;
        $promise = $factory->createClient($url);
        $client = Block\await($promise, Loop::get(), 10.0);
        /* @var $client Client */

        $message = $this->awaitMessage($client);
        $this->assertEquals('SessionInit', $message->MsgType);

        // try to pick first buffer
        $buffer = reset($message->SessionState->BufferInfos);
        if ($buffer === false) {
            $client->close();
            $this->markTestSkipped('Empty quassel core with no buffers?');
        }

        // fetch newest messages for this buffer
        $this->assertTrue($buffer instanceof BufferInfo);
        $client->writeBufferRequestBacklog($buffer->id, -1, -1, $maximum = 2, 0);

        $received = $this->awaitMessage($client);
        $this->assertTrue(isset($received[0]));
        $this->assertSame(1, $received[0]);

        // Quassel v0.13+ receives a `CoreInfo` at the beginning of the session,
        // so we await the next message
        if ($received[1] === 'CoreInfo') {
            $received = $this->awaitMessage($client);
        }

        $this->assertSame('BacklogManager', $received[1]);
        $this->assertSame('receiveBacklog', $received[3]);
        $this->assertSame($maximum, $received[7]);
        $this->assertTrue(is_array($received[9]));
        $this->assertLessThanOrEqual($maximum, count($received[9]));

        // try to pick newest message
        $newest = reset($received[9]);
        if ($newest === false) {
            $client->close();
            $this->markTestSkipped('No messages in first buffer?');
        }

        // poll for newer messages in all channels
        $this->assertTrue($newest instanceof Message);
        $client->writeBufferRequestBacklogAll($newest->id, -1, $maximum, 0);

        $received = $this->awaitMessage($client);
        $this->assertTrue(isset($received[0]));
        $this->assertSame(1, $received[0]);
        $this->assertSame('BacklogManager', $received[1]);
        $this->assertSame('receiveBacklogAll', $received[3]);
        $this->assertSame($maximum, $received[6]);
        $this->assertTrue(is_array($received[8]));
        $this->assertLessThanOrEqual($maximum, count($received[8]));

        // try to pick newest message
        $newest = reset($received[8]);
        if ($newest === false) {
            $client->close();
            $this->markTestSkipped('No newer messages found?');
        }

        $this->assertTrue($newest instanceof Message);
        $this->assertInstanceOf('DateTime', $newest->timestamp);

        $client->close();
    }

    public function testCreateClientWithInvalidAuthUrlRejects()
    {
        $factory = new Factory();

        $url = rawurlencode(self::$username) . ':@' . self::$host;
        $promise = $factory->createClient($url);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    private function awaitMessage(Client $client)
    {
        try {
            return Block\await(new Promise(function ($resolve, $reject) use ($client) {
                $client->once('data', $resolve);

                $client->once('error', $reject);
                $client->once('close', $reject);
            }), Loop::get(), 10.0);
        } catch (\React\Promise\Timer\TimeoutException $e) {
            $this->markTestIncomplete('Unhandled race condition, please retry');
        }
    }
}
