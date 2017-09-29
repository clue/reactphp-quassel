<?php

use Clue\React\Quassel\Io\Binary;

class BinaryTest extends TestCase
{
    public function testZero()
    {
        $this->assertEquals("\0\0\0\0", Binary::writeUInt32(0));
        $this->assertEquals(0, Binary::readUInt32("\0\0\0\0"));
    }

    public function test18()
    {
        $this->assertEquals("\0\0\0\x12", Binary::writeUInt32(18));
        $this->assertEquals(18, Binary::readUInt32("\0\0\0\x12"));
    }
}
