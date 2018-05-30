<?php

use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;
use React\Stream\ThroughStream;

class ClientTest extends TestCase
{
    public function setUp()
    {
        $this->stream = $this->getMockBuilder('React\Stream\DuplexStreamInterface')->getMock();
        $this->protocol = $this->getMockBuilder('Clue\React\Quassel\Io\Protocol')->disableOriginalConstructor()->getMock();
        $this->splitter = $this->getMockBuilder('Clue\React\Quassel\Io\PacketSplitter')->disableOriginalConstructor()->getMock();

        $this->client = new Client($this->stream, $this->protocol, $this->splitter);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCtorOptionalArgs()
    {
        $this->stream = $this->getMockBuilder('React\Stream\DuplexStreamInterface')->getMock();
        new Client($this->stream);
    }

    public function testClosingClientClosesUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('close');
        $this->client->close();
    }

    public function testClosingClientEmitsCloseEventAndRemovesListeners()
    {
        $this->client->on('close', $this->expectCallableOnce());
        $this->assertCount(1, $this->client->listeners('close'));

        $this->client->close();
        $this->assertEquals(array(), $this->client->listeners('close'));
    }

    public function testIsReadableWillReturnFromUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('isReadable')->willReturn(true);
        $this->assertTrue($this->client->isReadable());
    }

    public function testIsWritableWillReturnFromUnderlyingStream()
    {
        $this->stream->expects($this->once())->method('isWritable')->willReturn(true);
        $this->assertTrue($this->client->isWritable());
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
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $this->assertEquals($dest, $this->client->pipe($dest));
    }

    public function testCloseEventWillBeForwarded()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol, $this->splitter);

        $this->client->on('close', $this->expectCallableOnce());
        $this->stream->emit('close');
    }

    public function testDrainEventWillBeForwarded()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol, $this->splitter);

        $this->client->on('drain', $this->expectCallableOnce());
        $this->stream->emit('drain');
    }

    public function testEndEventWillBeForwardedAndClose()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol);

        $this->client->on('end', $this->expectCallableOnce());
        $this->stream->on('close', $this->expectCallableOnce());
        $this->stream->end();
    }

    public function testErrorEventWillBeForwardedAndClose()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol, $this->splitter);

        $e = new \RuntimeException();

        $this->client->on('error', $this->expectCallableOnceWith($e));
        $this->stream->on('close', $this->expectCallableOnce());
        $this->stream->emit('error', array($e));
    }

    public function testDataEventWillNotBeForwardedIfItIsAnIncompletePacket()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol, $this->splitter);

        $this->splitter->expects($this->once())->method('push')->with("hello", array($this->client, 'handlePacket'));
        $this->client->on('data', $this->expectCallableNever());

        $this->stream->emit('data', array("hello"));
    }

    public function testDataEventWillBeForwardedFromSplitterThroughProtocolParser()
    {
        $this->protocol->expects($this->once())->method('parseVariantPacket')->with('hello')->willReturn('parsed');
        $this->client->on('data', $this->expectCallableOnceWith('parsed'));

        $this->client->handlePacket('hello');
    }

    public function testDataEventWillEmitErrorAndCloseIfSizeExceeded()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol);

        $this->client->on('data', $this->expectCallableNever());
        $this->client->on('error', $this->expectCallableOnce());
        $this->client->on('close', $this->expectCallableOnce());

        $this->stream->emit('data', array("\xFF\xFF\xFF\xFF"));
    }

    public function testEndEventWillEmitErrorIfDataIsBuffered()
    {
        $this->stream = new ThroughStream();
        $this->client = new Client($this->stream, $this->protocol);

        $this->client->on('data', $this->expectCallableNever());
        $this->client->on('end', $this->expectCallableNever());
        $this->client->on('error', $this->expectCallableOnce());
        $this->client->on('close', $this->expectCallableOnce());

        $this->stream->emit('data', array("\x00\x00"));
        $this->stream->emit('end');
    }

    public function testWriteArrayWillWriteMap()
    {
        $this->expectWriteMap();
        $this->client->write(array('hello' => 'world'));
    }

    public function testEndWithArrayWillWriteMap()
    {
        $this->expectWriteMap();
        $this->stream->expects($this->once())->method('end');
        $this->client->end(array('hello' => 'world'));
    }

    public function testEndWithoutDataWillNowWrite()
    {
        $this->stream->expects($this->never())->method('write');
        $this->stream->expects($this->once())->method('end');
        $this->client->end();
    }

    public function testWriteClientInit()
    {
        $this->expectWriteMap();
        $this->client->writeClientInit();
    }

    public function testWriteClientLogin()
    {
        $this->expectWriteMap();
        $this->client->writeClientLogin('a', 'b');
    }

    public function testWriteCoreSetupData()
    {
        $this->expectWriteMap();
        $this->client->writeCoreSetupData('user', 'pass', 'PQSql', array('password' => 'test'));
    }

    public function testWriteHeartBeatReply()
    {
        $dt = new \DateTime();

        $this->protocol->expects($this->once())->method('serializeVariantPacket')->with(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            $dt
        ));
        $this->splitter->expects($this->once())->method('writePacket');

        $this->client->writeHeartBeatReply($dt);
    }

    public function testWriteHeartBeatRequest()
    {
        $dt = new \DateTime();

        $this->protocol->expects($this->once())->method('serializeVariantPacket')->with(array(
            Protocol::REQUEST_HEARTBEAT,
            $dt
        ));
        $this->splitter->expects($this->once())->method('writePacket');

        $this->client->writeHeartBeatRequest($dt);
    }

    public function testWriteHeartBeatRequestWithoutTimestampSendsCurrentTimestamp()
    {
        $that = $this;
        $this->protocol->expects($this->once())->method('serializeVariantPacket')->with($this->callback(function ($value) use ($that) {
            $that->assertCount(2, $value);
            $that->assertEquals(Protocol::REQUEST_HEARTBEAT, $value[0]);

            $that->assertInstanceOf('DateTime', $value[1]);
            $that->assertEquals(microtime(true), $value[1]->getTimestamp(), '', 2);
            return true;
        }));
        $this->splitter->expects($this->once())->method('writePacket');

        $this->client->writeHeartBeatRequest();
    }

    private function expectWriteMap()
    {
        $this->protocol->expects($this->once())->method('serializeVariantPacket');
        $this->splitter->expects($this->once())->method('writePacket');
        $this->stream->expects($this->once())->method('write');
    }
}
