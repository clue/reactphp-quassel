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
        if (isset($data[0]) && ($data[0] === self::REQUEST_HEARTBEAT || $data[0] === self::REQUEST_HEARTBEATREPLY)) {
            $data[1]->modify($data[1]->getOffset() .  ' seconds');
        }

        return $data;
    }
}
