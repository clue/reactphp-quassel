<?php

namespace Clue\React\Quassel;

use Clue\React\Quassel\Io\Prober;
use Clue\React\Quassel\Io\Protocol;
use React\EventLoop\LoopInterface;
use React\Promise;
use React\Socket\ConnectorInterface;
use React\Socket\Connector;
use React\Stream\DuplexStreamInterface;
use InvalidArgumentException;

class Factory
{
    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null, Prober $prober = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }
        if ($prober === null) {
            $prober = new Prober();
        }
        $this->loop = $loop;
        $this->connector = $connector;
        $this->prober = $prober;
    }

    public function createClient($uri)
    {
        if (strpos($uri, '://') === false) {
            $uri= 'quassel://' . $uri;
        }
        $parts = parse_url($uri);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'quassel') {
            return Promise\reject(new InvalidArgumentException('Given argument "' . $uri. '" is not a valid Quassel URI'));
        }
        if (!isset($parts['port'])) {
            $parts['port'] = 4242;
        }

        $args = array();
        if (isset($parts['query'])) {
            parse_str($parts['query'], $args);
        }

        // establish low-level TCP/IP connection to Quassel IRC core
        $promise = $this->connector->connect($parts['host'] . ':' . $parts['port']);

        // probe protocol once connected
        $probe = 0;
        $connector = $this->connector;
        $prober = $this->prober;
        $promise = $promise->then(function (DuplexStreamInterface $stream) use ($prober, &$probe, $connector, $parts) {
            return $prober->probe($stream)->then(
                function ($ret) use (&$probe, $stream) {
                    // probe returned successfully, create new client for this stream
                    $probe = $ret;

                    return $stream;
                },
                function ($e) use ($connector, $parts) {
                    // probing failed
                    if ($e->getCode() === Prober::ERROR_CLOSED) {
                        // legacy servers will terminate connection while probing
                        // let's just open a new connection and assume default probe
                        return $connector->connect($parts['host'] . ':' . $parts['port']);
                    }
                    throw $e;
                }
            );
        });

        // decorate client once probing is finished
        $promise = $promise->then(
            function (DuplexStreamInterface $stream) use (&$probe) {
                return new Client($stream, Protocol::createFromProbe($probe));
            }
        );

        // automatic login if username/password is given as part of URI
        if (isset($parts['user']) || isset($parts['pass'])) {
            $that = $this;
            $promise = $promise->then(function (Client $client) use ($that, $parts) {
                return $that->awaitLogin(
                    $client,
                    isset($parts['user']) ? urldecode($parts['user']) : '',
                    isset($parts['pass']) ? urldecode($parts['pass']) : ''
                );
            });
        }

        // automatically send ping requests and await pong replies unless "?ping=0" is given
        // automatically reply to incoming ping requests with a pong unless "?pong=0" is given
        $ping = (!isset($args['ping'])) ? 60 : (float)$args['ping'];
        $pong = (!isset($args['pong']) || $args['pong']) ? true : false;
        if ($ping !== 0.0 || $pong) {
            $loop = $this->loop;
            $promise = $promise->then(function (Client $client) use ($loop, $ping, $pong) {
                $timer = null;
                if ($ping !== 0.0) {
                    // send heartbeat message every X seconds to check dropped connection
                    $timer = $loop->addPeriodicTimer($ping, function () use ($client) {
                        $client->writeHeartBeatRequest();
                    });

                    // stop heartbeat timer once connection closes
                    $client->on('close', function () use ($loop, &$timer) {
                        $loop->cancelTimer($timer);
                        $timer = null;
                    });
                }

                $client->on('data', function ($message) use ($client, $pong, &$timer, $loop) {
                    // reply to incoming ping messages with pong
                    if (isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEAT && $pong) {
                        $client->writeHeartBeatReply($message[1]);
                    }

                    // restart heartbeat timer once data comes in
                    if ($timer !== null) {
                        $loop->cancelTimer($timer);
                        $timer = $loop->addPeriodicTimer($timer->getInterval(), $timer->getCallback());
                    }
                });

                return $client;
            });
        }

        return $promise;
    }

    /** @internal */
    public function awaitLogin(Client $client, $user, $pass)
    {
        return new Promise\Promise(function ($resolve, $reject) use ($client, $user, $pass) {
            // handle incoming response messages
            $client->on('data', $handler = function ($data) use ($resolve, $reject, $client, $user, $pass, &$handler) {
                $type = null;
                if (is_array($data) && isset($data['MsgType'])) {
                    $type = $data['MsgType'];
                }

                // continue to login if connection is initialized
                if ($type === 'ClientInitAck') {
                    if (!isset($data['Configured']) || !$data['Configured']) {
                        $reject(new \RuntimeException('Unable to log in to unconfigured Quassel IRC core'));
                        return $client->close();
                    }

                    $client->writeClientLogin($user, $pass);

                    return;
                }

                // reject if core rejects initialization
                if ($type === 'ClientInitReject') {
                    $reject(new \RuntimeException('Connection rejected by Quassel core: ' . $data['Error']));
                    return $client->close();
                }

                // reject promise if login is rejected
                if ($type === 'ClientLoginReject') {
                    $reject(new \RuntimeException('Unable to log in: ' . $data['Error']));
                    return $client->close();
                }

                // resolve promise if login is successful
                if ($type === 'ClientLoginAck') {
                    $client->removeListener('data', $handler);
                    $handler = null;
                    $resolve($client);

                    return;
                }

                // otherwise reject if we receive an unexpected message
                $reject(new \RuntimeException('Received unexpected "' . $type . '" message during login'));
                $client->close();
            });

            // reject promise if client emits error
            $client->on('error', function ($error) use ($reject) {
                $reject($error);
            });

            // reject promise if client closes while waiting for login
            $client->on('close', function () use ($reject) {
                $reject(new \RuntimeException('Unexpected close'));
            });

            $client->writeClientInit();
        });
    }
}
