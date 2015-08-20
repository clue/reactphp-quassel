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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCanNotTransportListStartingWithString()
    {
        $this->protocol->writeVariantList(array('does', 'not', 'work'));
    }

    public function testInitDataWireFormatWillBeRepresentedLikeLegacyProtocol()
    {
        // the message as it is sent over the wire
        $message = array(Protocol::REQUEST_INITDATA, 'Network', '1', 'k1', 'v1', 'k2', 'v2');

        // the actual message interpretation (in line with legacy protocol wire format)
        $expected = array(Protocol::REQUEST_INITDATA, 'Network', '1', array('k1' => 'v1', 'k2' => 'v2'));

        $this->assertEquals($expected, $this->protocol->readVariant($this->protocol->writeVariantList($message)));
    }
}
