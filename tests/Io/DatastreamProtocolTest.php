<?php

namespace Clue\Tests\React\Quassel\Io;

use Clue\React\Quassel\Io\Protocol;
use Clue\QDataStream\Writer;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;

class DatastreamProtocolTest extends AbstractProtocolTest
{
    /**
     * @before
     */
    public function setUpProtocol()
    {
        $this->protocol = Protocol::createFromProbe(Protocol::TYPE_DATASTREAM);
    }

    public function testIsNotLegacy()
    {
        $this->assertFalse($this->protocol->isLegacy());
    }

    public function testCanNotTransportListStartingWithString()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->protocol->serializeVariantPacket(array('does', 'not', 'work'));
    }

    public function testInitDataWireFormatWillBeRepresentedLikeLegacyProtocol()
    {
        // the message as it is sent over the wire
        $message = array(Protocol::REQUEST_INITDATA, 'Network', '1', 'k1', 'v1', 'k2', 'v2');

        // the actual message interpretation (in line with legacy protocol wire format)
        $expected = array(Protocol::REQUEST_INITDATA, 'Network', '1', (object)array('k1' => 'v1', 'k2' => 'v2'));

        $this->assertEquals($expected, $this->protocol->parseVariantPacket($this->protocol->serializeVariantPacket($message)));
    }

    public function testReceiveHeartBeatRequestWithCorrectTimeZone()
    {
        date_default_timezone_set('Europe/Berlin');

        $writer = new Writer();
        $writer->writeQVariantList(array(
            Protocol::REQUEST_HEARTBEAT,
            new QVariant(new \DateTime('2016-09-24 14:20:00.123+00:00'), Types::TYPE_QDATETIME)
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(2, $values);
        $this->assertEquals(Protocol::REQUEST_HEARTBEAT, $values[0]);
        $this->assertEquals(new \DateTime('2016-09-24 16:20:00.123', new \DateTimeZone('Europe/Berlin')), $values[1]);
    }

    public function testInitDataWillTurnArgumentListIntoMap()
    {
        $writer = new Writer();
        $writer->writeQVariantList(array(
            Protocol::REQUEST_INITDATA,
            'Network',
            '1',
            'a',
            1,
            'b',
            2
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(4, $values);
        $this->assertCount(2, (array)$values[3]);

        $expected = (object)array(
            'a' => 1,
            'b' => 2
        );
        $this->assertEquals($expected, $values[3]);
    }

    public function testInitDataNetworkUpcastedToLogicFormat()
    {
        $writer = new Writer();
        $writer->writeQVariantList(array(
            Protocol::REQUEST_INITDATA,
            'Network',
            '1',
            'IrcUsersAndChannels',
            array(
                'Users' => array(
                    'nick' => array(
                        'a',
                        'b'
                    ),
                    'here' => array(
                        true,
                        false
                    )
                )
            )
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(4, $values);
        $this->assertCount(1, (array)$values[3]);

        $expected = (object)array(
            'Users' => array(
                (object)array(
                    'nick' => 'a',
                    'here' => true
                ),
                (object)array(
                    'nick' => 'b',
                    'here' => false
                )
            ),
            'Channels' => array()
        );
        $this->assertEquals($expected, $values[3]->IrcUsersAndChannels);
    }
}
