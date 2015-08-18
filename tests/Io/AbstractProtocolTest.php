<?php

use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Io\Binary;
use Clue\React\Quassel\Io\PacketParser;
use Clue\QDataStream\Reader;

abstract class AbstractProtocolTest extends TestCase
{
    protected $protocol;

    public function testVariantList()
    {
        $in = array(1, 'first', 'second', 10, false);

        $packet = $this->protocol->writeVariantList($in);

        $this->assertEquals($in, $this->protocol->readVariant($packet));
    }

    public function testVariantMap()
    {
        $in = array('hello' => 'world', 'number' => 10, 'boolean' => true);

        $packet = $this->protocol->writeVariantMap($in);

        $this->assertEquals($in, $this->protocol->readVariant($packet));
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
