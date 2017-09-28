<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Reader;

class LegacyProtocol extends Protocol
{
    public function isLegacy()
    {
        return true;
    }

    public function writeVariantList(array $list)
    {
        $writer = new Writer($this->userTypeWriter);

        // legacy protocols prefixes list with type information
        $writer->writeQVariant($list);

        return (string)$writer;
    }

    public function writeVariantMap(array $map)
    {
        $writer = new Writer();

        // legacy protocol prefixes map with type information
        $writer->writeQVariant($map);

        return (string)$writer;
    }

    public function readVariant($packet)
    {
        $reader = new Reader($packet, $this->userTypeReader);

        // legacy protcol always uses type prefix, so just read as variant
        $q = $reader->readQVariant();

        // ping requests will actually be sent as QTime which assumes UTC timezone
        // times will be returned with local timezone, so account for offset to UTC
        if (isset($q[0]) && ($q[0] === self::REQUEST_HEARTBEAT || $q[0] === self::REQUEST_HEARTBEATREPLY)) {
            $q[1]->modify($q[1]->getOffset() .  ' seconds');
        }

        return $q;
    }
}
