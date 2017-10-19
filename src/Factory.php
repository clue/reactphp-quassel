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
        return $promise->then(
            function (DuplexStreamInterface $stream) use (&$probe) {
                return new Client($stream, Protocol::createFromProbe($probe));
            }
        );
    }
}
