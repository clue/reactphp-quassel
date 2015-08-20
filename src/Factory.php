<?php

namespace Clue\React\Quassel;

use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Clue\React\Quassel\Io\Handshaker;
use Clue\React\Quassel\Io\Prober;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\SocketClient\Connector;
use Clue\React\Quassel\Io\Protocol;
use React\Promise;
use InvalidArgumentException;

class Factory
{
    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null, Prober $prober = null)
    {
    	if ($connector === null) {
    		$resolverFactory = new ResolverFactory();
    		$resolver = $resolverFactory->create('8.8.8.8', $loop);
    		$connector = new Connector($loop, $resolver);
    	}
        if ($prober === null) {
            $prober = new Prober();
        }
        $this->loop = $loop;
        $this->connector = $connector;
        $this->prober = $prober;
    }

    public function createClient($address)
    {
        if (strpos($address, '://') === false) {
            $address = 'tcp://' . $address;
        }
        $parts = parse_url($address);
        if (!$parts || !isset($parts['host'])) {
            return Promise\reject(new InvalidArgumentException('Given argument "' . $address . '" is not a valid URI'));
        }
        if (!isset($parts['port'])) {
            $parts['port'] = 4242;
        }

        // default to automatic probing protocol unless scheme is explicitly given
        $probe = 0;
        if (isset($parts['scheme'])) {
            if ($parts['scheme'] === 'legacy') {
                $probe = Protocol::TYPE_LEGACY;
            } elseif ($parts['scheme'] !== 'tcp') {
                return Promise\reject(new InvalidArgumentException('Given URI scheme "' . $parts['scheme'] . '" is invalid'));
            }
        }

        $promise = $this->connector->create($parts['host'], $parts['port']);

        // protocol probe not already set
        if ($probe === 0) {
            $connector = $this->connector;
            $prober = $this->prober;

            $promise = $promise->then(function (Stream $stream) use ($prober, &$probe, $connector, $parts) {
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
                            return $connector->create($parts['host'], $parts['port']);
                        }
                        throw $e;
                    }
                );
            });
        }

        return $promise->then(
            function (Stream $stream) use (&$probe) {
                return new Client($stream, Protocol::createFromProbe($probe));
            }
        );
    }
}
