<?php

use Clue\React\Quassel\Io\Binary;

class BinaryTest extends TestCase
{
    public function setUp()
    {
        $this->binary = new Binary();
    }

    public function testZero()
    {
        $this->assertEquals("\0\0\0\0", $this->binary->writeUInt32(0));
        $this->assertEquals(0, $this->binary->readUInt32("\0\0\0\0"));
    }

    public function test18()
    {
        $this->assertEquals("\0\0\0\x12", $this->binary->writeUInt32(18));
        $this->assertEquals(18, $this->binary->readUInt32("\0\0\0\x12"));
    }
}
