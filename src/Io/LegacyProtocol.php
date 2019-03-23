<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Reader;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;

/** @internal */
class LegacyProtocol extends Protocol
{
    public function isLegacy()
    {
        return true;
    }

    public function serializeVariantPacket(array $data)
    {
        // legacy ping requests will actually be sent as QTime which assumes UTC timezone
        if (isset($data[0]) && ($data[0] === self::REQUEST_HEARTBEAT || $data[0] === self::REQUEST_HEARTBEATREPLY)) {
            $dt = clone $data[1];
            $dt->setTimeZone(new \DateTimeZone('UTC'));
            $data[1] = new QVariant($dt, Types::TYPE_QTIME);
        }

        // legacy protocol prefixes both list and map with variant information
        $writer = new Writer($this->userTypeWriter);
        $writer->writeQVariant($data);

        return (string)$writer;
    }

    public function parseVariantPacket($packet)
    {
        // legacy protcol always uses type prefix, so just read as variant
        $reader = new Reader($packet, $this->userTypeReader);
        $data = $reader->readQVariant();

        // ping requests will actually be sent as QTime which assumes UTC timezone
        // times will be returned with local timezone, so account for offset to UTC
        if (is_array($data) && isset($data[0]) && ($data[0] === self::REQUEST_HEARTBEAT || $data[0] === self::REQUEST_HEARTBEATREPLY)) {
            $data[1]->modify($data[1]->getOffset() .  ' seconds');
        }

        // Don't upcast legacy InitData for "Network" to newer datagram variant.
        // The legacy protocol uses a rather inefficient format which repeats the
        // same keys for each and every object, but it's "logic" and easy to work with.
        // We use a similar "logic" representation on the data:
        // The "IrcUsersAndChannels" structure always contains the keys "Users" and "Channels"
        // both keys always consist of a list of objects with additional details.
        // https://github.com/quassel/quassel/commit/208ccb6d91ebb3c26a67c35c11411ba3ab27708a#diff-c3c5a4e63a0b757912ba28686747b040
        if (is_array($data) && isset($data[0]) && $data[0] === self::REQUEST_INITDATA && $data[1] === 'Network' && isset($data[3]->IrcUsersAndChannels)) {
            $data[3]->IrcUsersAndChannels = (object)array(
                'Users' => array_values((array)$data[3]->IrcUsersAndChannels->users),
                'Channels' => array_values((array)$data[3]->IrcUsersAndChannels->channels)
            );
        }

        return $data;
    }
}
