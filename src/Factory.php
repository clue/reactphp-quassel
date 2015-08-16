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
            $address = 'dummy://' . $address;
        }
        $parts = parse_url($address);
        if (!$parts || !isset($parts['host'])) {
            return;
        }
        if (!isset($parts['port'])) {
            $parts['port'] = 4242;
        }

        $connector = $this->connector;
        $prober = $this->prober;

        return $connector->create($parts['host'], $parts['port'])->then(
            function (Stream $stream) use ($prober, $connector, $parts) {
                $probe = 0;

                return $prober->probe($stream)->then(
                    function ($ret) use (&$probe, $stream) {
                        // probe returned successfully, create new client for this stream
                        $probe = $ret;

                        return $stream;
                    },
                    function ($e) use ($connector, $parts) {
                        if ($e->getCode() === Prober::ERROR_CLOSED) {
                            // legacy servers will terminate connection while probing
                            return $connector->create($parts['host'], $parts['port']);
                        }
                        throw $e;
                    }
                )->then(
                    function (Stream $stream) use (&$probe) {
                        return new Client($stream, Protocol::createFromProbe($probe));
                    }
                );
            }
        );
    }
}
