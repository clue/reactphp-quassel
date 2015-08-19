<?php

use Clue\React\Quassel\Io\Protocol;

class LegacyProtocolTest extends AbstractProtocolTest
{
    public function setUp()
    {
        $this->protocol = Protocol::createFromProbe(Protocol::TYPE_LEGACY);
    }

    public function testIsLegacy()
    {
        $this->assertTrue($this->protocol->isLegacy());
    }
}
