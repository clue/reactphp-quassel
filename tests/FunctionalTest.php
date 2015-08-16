<?php

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Quassel\Factory;
use Clue\React\Block\Blocker;
use Clue\React\Quassel\Client;
use React\Promise\Deferred;
use Clue\React\Quassel\Io\Protocol;

class FunctionalTest extends TestCase
{
    private static $host;
    private static $username;
    private static $password;

    private static $loop;
    private static $blocker;

    public static function setUpBeforeClass()
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

        self::$loop = LoopFactory::create();
        self::$blocker = new Blocker(self::$loop);
    }

    public function setUp()
    {
        if (!self::$host) {
            $this->markTestSkipped('No ENV QUASSEL_HOST (plus optionally QUASSEL_USER and QUASSEL_PASS) given');
        }
    }

    public function testCreateClient()
    {
        $factory = new Factory(self::$loop);
        $promise = $factory->createClient(self::$host);

        $client = self::$blocker->awaitOne($promise);

        return $client;
    }

    /**
     * @depends testCreateClient
     * @param Client $client
     */
    public function testSendClientInit(Client $client)
    {
        $client->sendClientInit();

        $message = $this->awaitMessage($client);
        $this->assertEquals('ClientInitAck', $message['MsgType']);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testSendClientInit
     *
     * @param Client $client
     * @param array  $message
     */
    public function testSendCoreSetupData(Client $client, $message)
    {
        if ($message['Configured']) {
            $this->markTestSkipped('Given core already configured, can not set-up');
        }

        $client->sendCoreSetupData(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('CoreSetupAck', $message['MsgType']);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testSendClientInit
     *
     * @param Client $client
     * @param array  $message
     */
    public function testSendClientLogin(Client $client, $message)
    {
        $client->sendClientLogin(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('ClientLoginAck', $message['MsgType']);

        return $message;
    }

    /**
     * @depends testCreateClient
     *
     * @param Client $client
     */
    public function testSendHeartBeat(Client $client)
    {
        $time = new \DateTime();

        $deferred = new Deferred();

        $callback = function ($message) use ($deferred, &$callback, $client) {
            if (isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEATREPLY) {
                $client->removeListener('message', $callback);
                $deferred->resolve($message[1]);
            }
        };

        $client->on('message', $callback);

        $client->sendHeartBeatRequest($time);

        $received = self::$blocker->awaitOne($deferred->promise());

        $this->assertEquals($time, $received);
    }

    /**
     * @depends testCreateClient
     */
    public function testClose(Client $client)
    {
        $deferred = new Deferred();
        $client->once('close', function () use ($deferred) {
            $deferred->resolve();
        });

        $client->close();

        return self::$blocker->awaitOne($deferred->promise());
    }

    private function awaitMessage(Client $client)
    {
        $deferred = new Deferred();

        $client->once('message', array($deferred, 'resolve'));
        $client->once('error', array($deferred, 'reject'));
        $client->once('close', array($deferred, 'reject'));

        return self::$blocker->awaitOne($deferred->promise());
    }
}
