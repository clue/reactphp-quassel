<?php

namespace Clue\React\Quassel;

use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Clue\React\Quassel\Io\Handshaker;
use Clue\React\Quassel\Io\Prober;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\SocketClient\Connector;

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

        return $this->connector->create($parts['host'], $parts['port'])->then(array($this, 'handleStream'));
    }

    /** @internal */
    public function handleStream(Stream $stream)
    {
        return $this->prober->probe($stream)->then(function ($probe) use ($stream) {
            // probe returned successfully, create new client
            // TODO: ignore $probe value for now, should check for protocol, compression and SSL
            return new Client($stream);
        });
    }
}
