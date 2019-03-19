<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Reader;
use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;
use InvalidArgumentException;

/** @internal */
class DatastreamProtocol extends Protocol
{
    public function isLegacy()
    {
        return false;
    }

    public function serializeVariantPacket(array $data)
    {
        if (isset($data[0]) && !is_integer($data[0])) {
            throw new InvalidArgumentException('List MUST start with an integer value in order to distinguish from map encoding');
        }

        // datastream protocol transports maps as list contents with UTF-8 keys
        // the list always starts with a key string, which can be used to tell apart from actual list contents
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L80
        if (!isset($data[0])) {
            $data = $this->mapToList($data);
        }

        // datastream protocol always uses list contents without variant prefix
        $writer = new Writer($this->userTypeWriter);
        $writer->writeQVariantList($data);

        return (string)$writer;
    }

    public function parseVariantPacket($packet)
    {
        $reader = new Reader($packet, $this->userTypeReader);

        // datastream protocol always uses list contents (even for maps)
        $data = $reader->readQVariantList();

        // if the first element is a string, then this is actually a map transported as a list
        // actual lists will always start with an integer request type
        if (is_string($data[0])) {
            // datastream protocol uses lists with UTF-8 keys
            // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L109
            return $this->listToMap($data);
        }

        if ($data[0] === self::REQUEST_INITDATA) {
            // make sure InitData is in line with legacy protocol wire format
            // first 3 elements are unchanged, everything else should be a map
            // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L383
            $data = array_slice($data, 0, 3) + array(3 => $this->listToMap(array_slice($data, 3)));
        }

        // Don't downcast newer datagram InitData for "Network" to older legacy variant.
        // The datastream protocol uses a much more network-efficient wire-protocol
        // which avoids repeating the same keys over and over again, but this
        // format is very hard to work with from a consumer's perspective.
        // Instead, we use a "logic" representation of the data inspired by the legacy protocol:
        // The "IrcUsersAndChannels" structure always contains the keys "Users" and "Channels"
        // both keys always consist of a list of objects with additional details.
        // https://github.com/quassel/quassel/commit/208ccb6d91ebb3c26a67c35c11411ba3ab27708a#diff-c3c5a4e63a0b757912ba28686747b040
        if (is_array($data) && isset($data[0]) && $data[0] === self::REQUEST_INITDATA && $data[1] === 'Network' && isset($data[3]->IrcUsersAndChannels)) {
            $new = (object)array(
                'Users' => array(),
                'Channels' => array()
            );
            foreach ($data[3]->IrcUsersAndChannels as $type => $all) {
                // each type is logically represented by a list of objects
                // initialize with empty list even if no records are found at all
                $list = array();
                foreach ($all as $key => $values) {
                    foreach ($values as $i => $value) {
                        if (!isset($list[$i])) {
                            $list[$i] = new \stdClass();
                        }
                        $list[$i]->$key = $value;
                    }
                }
                $new->$type = $list;
            }
            $data[3]->IrcUsersAndChannels = $new;
        }

        return $data;
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
     * @return \stdClass `map<string,mixed>`
     * @internal
     */
    public function listToMap(array $list)
    {
        $map = array();
        for ($i = 0, $n = count($list); $i < $n; $i += 2) {
            $map[$list[$i]] = $list[$i + 1];
        }
        return (object)$map;
    }
}
