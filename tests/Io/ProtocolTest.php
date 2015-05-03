<?php

use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Io\Binary;
use Clue\React\Quassel\Io\PacketParser;
use Clue\QDataStream\Reader;

class ProtocolTest extends TestCase
{
    public function setUp()
    {
        $this->protocol = new Protocol(new Binary());
    }

    public function testVariantList()
    {
        $in = array('first', 'second', 10, false);

        $packet = $this->protocol->writeVariantList($in);
		$reader = Reader::fromString($packet);

        $this->assertEquals($in, $reader->readVariant());
    }

    public function testVariantMap()
    {
        $in = array('hello' => 'world', 'number' => 10, 'boolean' => true);

        $packet = $this->protocol->writeVariantMap($in);
		$reader = Reader::fromString($packet);

        $this->assertEquals($in, $reader->readVariant());
    }

    private function readDataFromPacket($packet)
    {
        $parser = new PacketParser(new Binary());

        $variant = null;

        $parser->push($packet, function ($data) use (&$variant) {
            $variant = $data;
        });

        $this->assertNotNull($variant);

        return $variant;
    }
}
