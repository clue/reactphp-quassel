<?php

use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Io\Binary;
use Clue\React\Quassel\Io\PacketParser;
use Clue\QDataStream\Reader;
use Clue\QDataStream\QVariant;

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

    public function testUserTypeBufferId()
    {
        $packet = $this->protocol->writeVariantList(array(
            1000,
            new QVariant(10, 'BufferId')
        ));

        $out = $this->protocol->readVariant($packet);

        $this->assertEquals(array(1000, 10), $out);
    }
}
