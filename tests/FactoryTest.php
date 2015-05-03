<?php

use Clue\React\Quassel\Factory;
use React\Promise\Deferred;
class FactoryTest extends TestCase
{
    public function setUp()
    {
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
        $this->connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $this->prober = $this->getMockBuilder('Clue\React\Quassel\Io\Prober')->disableOriginalConstructor()->getMock();

        $this->factory = new Factory($this->loop, $this->connector, $this->prober);
    }

    public function testCtorOptionalArgs()
    {
        new Factory($this->loop);
    }

    public function testPassHostnameAndDefaultPortToConnector()
    {
        $deferred = new Deferred();
        $this->connector->expects($this->once())->method('create')->with($this->equalTo('example.com', 4242))->will($this->returnValue($deferred->promise()));
        $this->factory->createClient('example.com');
    }

    public function testPassHostnameAndPortToConnector()
    {
        $deferred = new Deferred();
        $this->connector->expects($this->once())->method('create')->with($this->equalTo('example.com', 1234))->will($this->returnValue($deferred->promise()));
        $this->factory->createClient('example.com:1234');
    }
}
