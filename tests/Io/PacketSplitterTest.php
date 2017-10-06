<?php

use Clue\React\Quassel\Io\PacketSplitter;

class PacketSplitterTest extends TestCase
{
    private $splitter;

    public function setUp()
    {
        $this->splitter = new PacketSplitter();
    }

    public function testWillEmitOnceCompletePacketIsWritten()
    {
        $this->splitter->push("\0\0", $this->expectCallableNever());
        $this->splitter->push("\0\4", $this->expectCallableNever());
        $this->splitter->push("te", $this->expectCallableNever());
        $this->splitter->push("st", $this->expectCallableOnce());
    }

    public function testWriteCompletePacketToSplitterWillEmitImmediately()
    {
        $packet = $this->splitter->writePacket('hello');

        $this->splitter->push($packet, $this->expectCallableOnce());
    }

    /**
     * @expectedException OverflowException
     */
    public function testWillThrowForHugePacket()
    {
        $this->splitter->push("\xFF\xFF\xFF\xFF", $this->expectCallableNever());
    }
}
