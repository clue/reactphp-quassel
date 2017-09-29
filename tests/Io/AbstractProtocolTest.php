<?php

use Clue\React\Quassel\Io\Protocol;
use Clue\QDataStream\QVariant;

abstract class AbstractProtocolTest extends TestCase
{
    protected $protocol;

    public function testVariantList()
    {
        $in = array(1, 'first', 'second', 10, false);

        $packet = $this->protocol->serializeVariantPacket($in);

        $this->assertEquals($in, $this->protocol->parseVariantPacket($packet));
    }

    public function testVariantMap()
    {
        $in = array('hello' => 'world', 'number' => 10, 'boolean' => true);

        $packet = $this->protocol->serializeVariantPacket($in);

        $this->assertEquals($in, $this->protocol->parseVariantPacket($packet));
    }

    public function testUserTypeBufferId()
    {
        $packet = $this->protocol->serializeVariantPacket(array(
            1000,
            new QVariant(10, 'BufferId')
        ));

        $out = $this->protocol->parseVariantPacket($packet);

        $this->assertEquals(array(1000, 10), $out);
    }
}
