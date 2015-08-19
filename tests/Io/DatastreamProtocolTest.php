<?php

use Clue\React\Quassel\Io\Protocol;

class DatastreamProtocolTest extends AbstractProtocolTest
{
    public function setUp()
    {
        $this->protocol = Protocol::createFromProbe(Protocol::TYPE_DATASTREAM);
    }

    public function testIsNotLegacy()
    {
        $this->assertFalse($this->protocol->isLegacy());
    }
}
