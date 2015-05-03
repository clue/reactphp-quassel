<?php

use Clue\React\Quassel\Io\PacketSplitter;
use Clue\React\Quassel\Io\Binary;

class PacketSplitterTest extends TestCase
{
    public function testCallback()
    {
        $splitter = new PacketSplitter(new Binary());

        $splitter->push("\0\0", $this->expectCallableNever());
        $splitter->push("\0\4", $this->expectCallableNever());
        $splitter->push("te", $this->expectCallableNever());
        $splitter->push("st", $this->expectCallableOnce());
    }
}
