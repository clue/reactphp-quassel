<?php

use Clue\React\Block;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Factory;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server;
use React\Socket\ConnectionInterface;
use Clue\React\Quassel\Io\Protocol;

class FactoryIntegrationTest extends TestCase
{
    public function testCreateClientCreatesConnection()
    {
        $loop = LoopFactory::create();

        $server = new Server(0, $loop);
        $server->on('connection', $this->expectCallableOnce());

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, $loop);
    }

    public function testCreateClientSendsProbeOverConnection()
    {
        $loop = LoopFactory::create();

        $server = new Server(0, $loop);

        $data = $this->expectCallableOnceWith("\x42\xb3\x3f\x00" . "\x00\x00\x00\x02" . "\x80\x00\x00\x01");
        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->on('data', $data);
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, $loop);
    }

    public function testCreateClientResolvesIfServerRespondsWithProbeResponse()
    {
        $loop = LoopFactory::create();

        $server = new Server(0, $loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient($uri);

        $client = Block\await($promise, $loop, 10.0);

        $this->assertTrue($client instanceof Client);
        $client->close();
    }

    public function testCreateClientCreatesSecondConnectionWithoutProbeIfConnectionClosesDuringProbe()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $once = $this->expectCallableOnce();
        $server->on('connection', function (ConnectionInterface $conn) use ($once) {
            $conn->on('data', function () use ($conn) {
                $conn->close();
            });
            $conn->on('data', $once);
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, $loop);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCreateClientRejectsIfServerRespondsWithInvalidData()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write('invalid');
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient($uri);

        Block\await($promise, $loop, 10.0);
    }

    public function testCreateClientWithAuthSendsClientInitAfterProbe()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $data = FactoryIntegrationTest::decode($packet);

            return (isset($data['MsgType']) && $data['MsgType'] === 'ClientInit');
        }));

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->on('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', $data);
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\sleep(0.1, $loop);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCreateClientWithAuthRejectsIfServerClosesAfterClientInit()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', function () use ($conn) {
                    $conn->close();
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\await($promise, $loop, 10.0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCreateClientWithAuthRejectsIfServerSendsInvalidTruncatedResponseAfterClientInit()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', function () use ($conn) {
                    $conn->end("\x00\x00");
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\await($promise, $loop, 10.0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCreateClientWithAuthRejectsIfServerSendsClientInitAckNotConfigured()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', function () use ($conn) {
                    // respond with not configured
                    $conn->write(FactoryIntegrationTest::encode(array(
                        'MsgType' => 'ClientInitAck',
                        'Configured' => false
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\await($promise, $loop, 10.0);
    }

    public function testCreateClientWithAuthSendsClientLoginAfterClientInit()
    {
        $loop = LoopFactory::create();
        $server = new Server(0, $loop);

        // expect login packet
        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $protocol = Protocol::createFromProbe(0x02);
            $data = $protocol->parseVariantPacket(substr($packet, 4));

            return (isset($data['MsgType'], $data['User'], $data['Password']) && $data['MsgType'] === 'ClientLogin');
        }));

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");
                $conn->once('data', function () use ($conn, $data) {
                    // expect login next
                    $conn->on('data', $data);

                    // response with successful init
                    $conn->write(FactoryIntegrationTest::encode(array(
                        'MsgType' => 'ClientInitAck',
                        'Configured' => true
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory($loop);
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\sleep(0.1, $loop);
    }

    public static function encode($data)
    {
        $protocol = Protocol::createFromProbe(0x02);
        $packet = $protocol->serializeVariantPacket($data);

        return pack('N', strlen($packet)) . $packet;
    }

    public static function decode($packet)
    {
        $protocol = Protocol::createFromProbe(0x02);

        return $protocol->parseVariantPacket(substr($packet, 4));
    }
}
