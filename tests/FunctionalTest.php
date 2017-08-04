<?php

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Quassel\Factory;
use Clue\React\Block;
use Clue\React\Quassel\Client;
use React\Promise\Promise;
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

        $client = Block\await($promise, self::$loop, 10.0);

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
        $this->assertEquals('ClientInitAck', $message['MsgType']);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testWriteClientInit
     *
     * @param Client $client
     * @param array  $message
     */
    public function testWriteCoreSetupData(Client $client, $message)
    {
        if ($message['Configured']) {
            $this->markTestSkipped('Given core already configured, can not set-up');
        }

        $client->writeCoreSetupData(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('CoreSetupAck', $message['MsgType']);

        return $message;
    }

    /**
     * @depends testCreateClient
     * @depends testWriteClientInit
     *
     * @param Client $client
     * @param array  $message
     */
    public function testWriteClientLogin(Client $client, $message)
    {
        $client->writeClientLogin(self::$username, self::$password);

        $message = $this->awaitMessage($client);
        $this->assertEquals('ClientLoginAck', $message['MsgType']);

        $message = $this->awaitMessage($client);
        $this->assertEquals('SessionInit', $message['MsgType']);

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
                if (isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEATREPLY) {
                    $client->removeListener('data', $callback);
                    $resolve($message[1]);
                }
            };

            $client->on('data', $callback);
        });

        $client->writeHeartBeatRequest($time);

        $received = Block\await($promise, self::$loop, 10.0);

        $this->assertEquals($time, $received);
    }

    /**
     * @depends testCreateClient
     */
    public function testClose(Client $client)
    {
        $promise = new Promise(function ($resolve) use ($client) {
            $client->once('close', $resolve);
        });

        $client->close();

        return Block\await($promise, self::$loop, 10.0);
    }

    private function awaitMessage(Client $client)
    {
        return Block\await(new Promise(function ($resolve, $reject) use ($client) {
            $client->once('data', $resolve);

            $client->once('error', $reject);
            $client->once('close', $reject);
        }), self::$loop, 10.0);
    }
}
