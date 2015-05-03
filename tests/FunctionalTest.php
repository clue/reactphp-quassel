<?php

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Quassel\Factory;
use Clue\React\Block\Blocker;
use Clue\React\Quassel\Client;
use React\Promise\Deferred;

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
        $deferred = new Deferred();

        $client->once('message', function ($message) use ($deferred) {
            if (isset($message['MsgType']) && $message['MsgType'] === 'ClientInitAck') {
                $deferred->resolve($message);
            } else {
                $deferred->reject($message);
            }
        });

        $client->sendClientInit();

        return self::$blocker->awaitOne($deferred->promise());
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

        $deferred = new Deferred();
        $client->once('message', function ($message) use ($deferred) {
            if (isset($message['MsgType']) && $message['MsgType'] === 'CoreSetupAck') {
                $deferred->resolve($message);
            } else {
                $deferred->reject($message);
            }
        });

        $client->sendCoreSetupData(self::$username, self::$password);

        return self::$blocker->awaitOne($deferred->promise());
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
        $deferred = new Deferred();
        $client->once('message', function ($message) use ($deferred) {
            if (isset($message['MsgType']) && $message['MsgType'] === 'ClientLoginAck') {
                $deferred->resolve($message);
            } else {
                $deferred->reject($message);
            }
        });

        $client->sendClientLogin(self::$username, self::$password);

        return self::$blocker->awaitOne($deferred->promise());
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
}
