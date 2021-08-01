<?php

namespace Clue\Tests\React\Quassel;

use Clue\React\Block;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Io\Protocol;
use React\EventLoop\Loop;
use React\Socket\Server;
use React\Socket\ConnectionInterface;

class FactoryIntegrationTest extends TestCase
{
    public function testCreateClientCreatesConnection()
    {
        $server = new Server(0);
        $server->on('connection', $this->expectCallableOnce());

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientSendsProbeOverConnection()
    {
        $server = new Server(0);

        $data = $this->expectCallableOnceWith("\x42\xb3\x3f\x00" . "\x00\x00\x00\x02" . "\x80\x00\x00\x01");
        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->on('data', $data);
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientResolvesIfServerRespondsWithProbeResponse()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        $client = Block\await($promise, Loop::get(), 10.0);

        $this->assertTrue($client instanceof Client);
        $client->close();
    }

    public function testCreateClientCreatesSecondConnectionWithoutProbeIfConnectionClosesDuringProbe()
    {
        $server = new Server(0);

        $once = $this->expectCallableOnce();
        $server->on('connection', function (ConnectionInterface $conn) use ($once) {
            $conn->on('data', function () use ($conn) {
                $conn->close();
            });
            $conn->on('data', $once);
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientRejectsIfServerRespondsWithInvalidData()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write('invalid');
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthSendsClientInitAfterProbe()
    {
        $server = new Server(0);

        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $data = FactoryIntegrationTest::decode($packet);

            return (isset($data->MsgType) && $data->MsgType === 'ClientInit');
        }));

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->on('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', $data);
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientWithAuthRejectsIfServerClosesAfterClientInit()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->on('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', function () use ($conn) {
                    $conn->close();
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthRejectsIfServerSendsClientInitRejectAfterClientInit()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");

                $conn->on('data', function () use ($conn) {
                    // respond with rejection
                    $conn->write(FactoryIntegrationTest::encode(array(
                        'MsgType' => 'ClientInitReject',
                        'Error' => 'Too old'
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthRejectsIfServerSendsUnknownMessageAfterClientInit()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");

                $conn->on('data', function () use ($conn) {
                    // respond with unknown message
                    $conn->write(FactoryIntegrationTest::encode(array(
                        'MsgType' => 'Unknown',
                        'Error' => 'Ignored'
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthRejectsIfServerSendsInvalidTruncatedResponseAfterClientInit()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
                $conn->on('data', function () use ($conn) {
                    $conn->end("\x00\x00");
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthRejectsIfServerSendsClientInitAckNotConfigured()
    {
        $server = new Server(0);

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
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        $this->setExpectedException('RuntimeException');
        Block\await($promise, Loop::get(), 10.0);
    }

    public function testCreateClientWithAuthSendsClientLoginAfterClientInit()
    {
        $server = new Server(0);

        // expect login packet
        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $protocol = Protocol::createFromProbe(0x02);
            $data = $protocol->parseVariantPacket(substr($packet, 4));

            return (isset($data->MsgType, $data->User, $data->Password) && $data->MsgType === 'ClientLogin');
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
        $factory = new Factory();
        $promise = $factory->createClient('user:pass@' . $uri);

        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientRespondsWithHeartBeatResponseAfterHeartBeatRequest()
    {
        $server = new Server(0);

        // expect heartbeat response packet
        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $protocol = Protocol::createFromProbe(0x02);
            $data = $protocol->parseVariantPacket(substr($packet, 4));

            return (isset($data[0]) && $data[0] === Protocol::REQUEST_HEARTBEATREPLY);
        }));

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");

                // expect heartbeat response next
                $conn->on('data', $data);

                Loop::addTimer(0.01, function() use ($conn) {
                    // response with successful init
                    $conn->write(FactoryIntegrationTest::encode(array(
                        Protocol::REQUEST_HEARTBEAT,
                        new \DateTime('2018-05-29 23:05:00')
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri);

        Block\sleep(0.1, Loop::get());

        $client = Block\await($promise, Loop::get());
        $client->close();
    }

    public function testCreateClientDoesNotRespondWithHeartBeatResponseIfPongIsDisabled()
    {
        $server = new Server(0);

        // expect no message in response
        $data = $this->expectCallableNever();

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");

                // expect no message in response
                $conn->on('data', $data);

                Loop::addTimer(0.01, function() use ($conn) {
                    // response with successful init
                    $conn->write(FactoryIntegrationTest::encode(array(
                        Protocol::REQUEST_HEARTBEAT,
                        new \DateTime('2018-05-29 23:05:00')
                    )));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri . '?pong=0');

        Block\sleep(0.1, Loop::get());

        $client = Block\await($promise, Loop::get());
        $client->close();
    }

    public function testCreateClientSendsHeartBeatRequestAtInterval()
    {
        $server = new Server(0);

        // expect heartbeat response packet
        $data = $this->expectCallableOnceWith($this->callback(function ($packet) {
            $protocol = Protocol::createFromProbe(0x02);
            $data = $protocol->parseVariantPacket(substr($packet, 4));

            return (isset($data[0]) && $data[0] === Protocol::REQUEST_HEARTBEAT);
        }));

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");

                // expect heartbeat request next
                $conn->on('data', $data);
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri . '?ping=0.05');

        Block\sleep(0.1, Loop::get());

        $client = Block\await($promise, Loop::get());
        $client->close();
    }

    public function testCreateClientSendsNoHeartBeatRequestIfServerKeepsSendingMessages()
    {
        $server = new Server(0);

        // expect heartbeat response packet
        $data = $this->expectCallableNever();

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");

                // expect no heartbeat request
                $conn->on('data', $data);

                // periodically send some dummy messages
                Loop::addPeriodicTimer(0.01, function() use ($conn) {
                    $conn->write(FactoryIntegrationTest::encode(array(0)));
                });
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri . '?ping=0.05&pong=0');

        Block\sleep(0.1, Loop::get());

        $client = Block\await($promise, Loop::get());
        $client->close();
    }

    public function testCreateClientClosesWithErrorIfServerDoesNotRespondToHeartBeatRequests()
    {
        $server = new Server(0);

        $server->on('connection', function (ConnectionInterface $conn) {
            $conn->once('data', function () use ($conn) {
                $conn->write("\x00\x00\x00\x02");
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri . '?ping=0.03');

        $client = Block\await($promise, Loop::get(), 0.1);

        $client->on('error', $this->expectCallableOnce());
        $client->on('close', $this->expectCallableOnce());
        Block\sleep(0.1, Loop::get());
    }

    public function testCreateClientSendsNoHeartBeatRequestIfPingIsDisabled()
    {
        $server = new Server(0);

        // expect heartbeat response packet
        $data = $this->expectCallableNever();

        $server->on('connection', function (ConnectionInterface $conn) use ($data) {
            $conn->once('data', function () use ($conn, $data) {
                $conn->write("\x00\x00\x00\x02");

                // expect no heartbeat request
                $conn->on('data', $data);
            });
        });

        $uri = str_replace('tcp://', '', $server->getAddress());
        $factory = new Factory();
        $promise = $factory->createClient($uri . '?ping=0');

        Block\sleep(0.1, Loop::get());

        $client = Block\await($promise, Loop::get());
        $client->close();
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
