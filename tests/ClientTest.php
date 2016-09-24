<?php

use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;
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

    public function testIsReadableWillReturnFromUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('isReadable')->willReturn(true);
        $this->assertTrue($this->client->isReadable());
    }

    public function testResumeWillResumeUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('resume');
        $this->client->resume();
    }

    public function testPauseWillPauseUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('pause');
        $this->client->pause();
    }

    public function testPipeWillReturnDestStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $this->assertEquals($dest, $this->client->pipe($dest));
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

    public function testSendHeartBeatRequest()
    {
        $dt = new \DateTime();

        $this->client->sendHeartBeatRequest($dt);
    }

    public function testSendHeartBeatReplyLegacyAsQTime()
    {
        $dt = new \DateTime();

        $this->protocol->expects($this->any())->method('isLegacy')->willReturn(true);
        $this->protocol->expects($this->once())->method('writeVariantList')->with(array(
            Protocol::REQUEST_HEARTBEAT,
            new QVariant($dt, Types::TYPE_QTIME)
        ));
        $this->splitter->expects($this->once())->method('writePacket');

        $this->client->sendHeartBeatRequest($dt);
    }

    public function testSendHeartBeatReplyNonLegacyAsQDateTime()
    {
        $dt = new \DateTime();

        $this->protocol->expects($this->any())->method('isLegacy')->willReturn(false);
        $this->protocol->expects($this->once())->method('writeVariantList')->with(array(
            Protocol::REQUEST_HEARTBEAT,
            new QVariant($dt, Types::TYPE_QDATETIME)
        ));
        $this->splitter->expects($this->once())->method('writePacket');

        $this->client->sendHeartBeatRequest($dt);
    }

    private function expectSendMap()
    {
        $this->protocol->expects($this->once())->method('writeVariantMap');
        $this->splitter->expects($this->once())->method('writePacket');
        $this->stream->expects($this->once())->method('write');
    }
}
