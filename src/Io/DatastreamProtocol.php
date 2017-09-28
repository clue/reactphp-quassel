<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Reader;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;
use InvalidArgumentException;

class DatastreamProtocol extends Protocol
{
    public function isLegacy()
    {
        return false;
    }

    public function writeVariantList(array $list)
    {
        if (isset($list[0]) && !is_integer($list[0])) {
            throw new InvalidArgumentException('List MUST start with an integer value in order to distinguish from map encoding');
        }

        $writer = new Writer($this->userTypeWriter);

        // datastream protocol just uses list contents
        $writer->writeQVariantList($list);

        return (string)$writer;
    }

    public function writeVariantMap(array $map)
    {
        $writer = new Writer();

        // datastream protocol just uses list contents with UTF-8 keys
        // the list always starts with a key string, which can be used to tell apart from actual list contents
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L80
        $writer->writeQVariantList($this->mapToList($map));

        return (string)$writer;
    }

    public function readVariant($packet)
    {
        $reader = new Reader($packet, $this->userTypeReader);

        // datastrema protocol always uses list contents (even for maps)
        $value = $reader->readQVariantList();

        // if the first element is a string, then this is actually a map transported as a list
        // actual lists will always start with an integer request type
        if (is_string($value[0])) {
            // datastream protocol uses lists with UTF-8 keys
            // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L109
            return $this->listToMap($value);
        }

        if ($value[0] === self::REQUEST_INITDATA) {
            // make sure InitData is in line with legacy protocol wire format
            // first 3 elements are unchanged, everything else should be a map
            // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L383
            return array_slice($value, 0, 3) + array(3 => $this->listToMap(array_slice($value, 3)));
        }

        return $value;
    }

    /**
     * converts the given map to a list
     *
     * @param mixed[]|array<mixed> $map
     * @return mixed[]|array<mixed>
     * @internal
     */
    public function mapToList($map)
    {
        $list = array();
        foreach ($map as $key => $value) {
            // explicitly pass key as UTF-8 byte array
            // pass value with automatic type detection
            $list []= new QVariant($key, Types::TYPE_QBYTE_ARRAY);
            $list []= $value;
        }
        return $list;
    }

    /**
     * converts the given list to a map
     *
     * @param mixed[]|array<mixed> $list
     * @return mixed[]|array<mixed>
     * @internal
     */
    public function listToMap($list)
    {
        $map = array();
        for ($i = 0, $n = count($list); $i < $n; $i += 2) {
            $map[$list[$i]] = $list[$i + 1];
        }
        return $map;
    }
}
