<?php

use Clue\React\Quassel\Client;
class ClientTest extends TestCase
{
    public function setUp()
    {
        $this->stream = $this->getMockBuilder('React\Stream\Stream')->disableOriginalConstructor()->getMock();
        $this->protocol = $this->getMockBuilder('Clue\React\Quassel\Io\Protocol')->disableOriginalConstructor()->getMock();
        $this->splitter = $this->getMockBuilder('Clue\React\Quassel\Io\PacketSplitter')->disableOriginalConstructor()->getMock();

        $this->client = new Client($this->stream, $this->protocol, $this->splitter);
    }

    public function testCtorOptionalArgs()
    {
        new Client($this->stream);
    }

    public function testClosingClientClosesUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('close');
        $this->client->close();
    }

    public function testSendClientInit()
    {
        $this->expectSendMap();
        $this->client->sendClientInit();
    }

    public function testSendClientLogin()
    {
        $this->expectSendMap();
        $this->client->sendClientLogin('a', 'b');
    }

    public function testSendCoreSetupData()
    {
        $this->expectSendMap();
        $this->client->sendCoreSetupData('user', 'pass', 'PQSql', array('password' => 'test'));
    }

    private function expectSendMap()
    {
        $this->protocol->expects($this->once())->method('writeVariantMap');
        $this->protocol->expects($this->once())->method('writePacket');
        $this->stream->expects($this->once())->method('write');
    }
}
