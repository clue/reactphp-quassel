<?php

namespace Clue\Tests\React\Quassel\Io;

use Clue\React\Quassel\Io\PacketSplitter;
use Clue\Tests\React\Quassel\TestCase;

class PacketSplitterTest extends TestCase
{
    private $splitter;

    /**
     * @before
     */
    public function setUpSplitter()
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

    public function testWillThrowForHugePacket()
    {
        $this->setExpectedException('OverflowException');
        $this->splitter->push("\xFF\xFF\xFF\xFF", $this->expectCallableNever());
    }
}
