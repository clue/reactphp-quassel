<?php

namespace Clue\Tests\React\Quassel\Io;

use Clue\React\Quassel\Io\Protocol;
use Clue\QDataStream\Writer;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;

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

    public function testInitDataNetworkUnchangedWithoutUsersAndChannels()
    {
        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_INITDATA,
            'Network',
            '1',
            $data = array(
                'a' => 1,
                'b' => 2,
            )
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(4, $values);
        $this->assertCount(2, (array)$values[3]);
        $this->assertEquals($data, (array)$values[3]);
    }

    public function testInitDataNetworkUpcastedToLogicFormatFromEmptyUsersAndChannels()
    {
        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_INITDATA,
            'Network',
            '1',
            array(
                'a' => 1,
                'IrcUsersAndChannels' => (object)array(
                    'users' => (object)array(),
                    'channels' => (object)array()
                ),
                'b' => 2,
            )
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $data = array(
            'Users' => array(),
            'Channels' => array()
        );

        $this->assertCount(4, $values);
        $this->assertCount(3, (array)$values[3]);
        $this->assertEquals($data, (array)$values[3]->IrcUsersAndChannels);
    }

    public function testInitDataNetworkUpcastedToLogicFormatWithoutKeysFromUsersMap()
    {
        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_INITDATA,
            'Network',
            '1',
            array(
                'a' => 1,
                'IrcUsersAndChannels' => (object)array(
                    'users' => (object)array(
                        'a' => (object)array(
                            'nick' => 'a'
                        ),
                        'b' => (object)array(
                            'nick' => 'b'
                        )
                    ),
                    'channels' => (object)array()
                ),
                'b' => 2,
            )
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $data = array(
            'Users' => array(
                (object)array(
                    'nick' => 'a'
                ),
                (object)array(
                    'nick' => 'b'
                )
            ),
            'Channels' => array()
        );

        $this->assertCount(4, $values);
        $this->assertCount(3, (array)$values[3]);
        $this->assertEquals($data, (array)$values[3]->IrcUsersAndChannels);
    }

    /**
     * The legacy protocol uses QTime which only transports time of the day and
     * not the actual day information. This means that reading in a QTime will
     * always assume today's date.
     */
    public function testReceiveHeartBeatReplyIsAlwaysToday()
    {
        date_default_timezone_set('UTC');

        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_HEARTBEAT,
            new QVariant(new \DateTime('2014-09-24 14:20:00+00:00'), Types::TYPE_QTIME)
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(2, $values);
        $this->assertEquals(Protocol::REQUEST_HEARTBEAT, $values[0]);
        $this->assertEquals(new \DateTime('14:20:00+00:00'), $values[1]);
    }

    public function testReceiveHeartBeatRequestWithCorrectTimeZone()
    {
        date_default_timezone_set('Etc/GMT-1');

        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_HEARTBEAT,
            new QVariant(new \DateTime('14:20:00.123+00:00'), Types::TYPE_QTIME)
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(2, $values);
        $this->assertEquals(Protocol::REQUEST_HEARTBEAT, $values[0]);
        $this->assertEquals(new \DateTime('15:20:00.123', new \DateTimeZone('Etc/GMT-1')), $values[1]);
    }

    public function testReceiveHeartBeatReplyWithCorrectTimeZone()
    {
        date_default_timezone_set('Etc/GMT-1');

        $writer = new Writer();
        $writer->writeQVariant(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            new QVariant(new \DateTime('14:20:00.123+00:00'), Types::TYPE_QTIME)
        ));

        $packet = (string)$writer;

        $values = $this->protocol->parseVariantPacket($packet);

        $this->assertCount(2, $values);
        $this->assertEquals(Protocol::REQUEST_HEARTBEATREPLY, $values[0]);
        $this->assertEquals(new \DateTime('15:20:00.123', new \DateTimeZone('Etc/GMT-1')), $values[1]);
    }
}
