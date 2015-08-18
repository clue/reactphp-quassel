<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Types;
use Clue\QDataStream\Reader;
use Clue\QDataStream\QVariant;

class Protocol
{
    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L30
    const MAGIC = 0x42b33f00;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L32
    const TYPE_INTERNAL = 0x00;
    const TYPE_LEGACY = 0x01;
    const TYPE_DATASTREAM = 0x02;
    const TYPELIST_END = 0x80000000;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L39
    const FEATURE_ENCRYPTION = 0x01;
    const FEATURE_COMPRESSION = 0x02;

    const REQUEST_INVALID = 0;
    const REQUEST_SYNC = 1;
    const REQUEST_RPCCALL = 2;
    const REQUEST_INITREQUEST = 3;
    const REQUEST_INITDATA = 4;
    const REQUEST_HEARTBEAT = 5;
    const REQUEST_HEARTBEATREPLY = 6;

    private $binary;
    private $userTypeReader;
    private $userTypeWriter;
    private $legacy = true;

    public static function createFromProbe($probe)
    {
        $protocol = new Protocol(new Binary());

        if ($probe & self::TYPE_DATASTREAM) {
            $protocol->legacy = false;
        }

        return $protocol;
    }

    public function __construct(Binary $binary)
    {
        $this->binary = $binary;
        $this->types = new Types();

        $this->userTypeReader = array(
            // All required by SessionInit
            'NetworkId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'Identity' => function (Reader $reader) {
                return $reader->readQVariantMap();
            },
            'IdentityId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'BufferInfo' => function (Reader $reader) {
                return array(
                    'id'      => $reader->readUInt(),
                    'network' => $reader->readUInt(),
                    'type'    => $reader->readUShort(),
                    'group'   => $reader->readUInt(),
                    'name'    => $reader->readQByteArray(),
                );
            },
            // all required by "Network" InitRequest
            'Network::Server' => function (Reader $reader) {
                return $reader->readQVariantMap();
            },
            // unknown source?
            'BufferId' => function(Reader $reader) {
                return $reader->readUInt();
            },
            'Message' => function (Reader $reader) {
                return array(
                    'id'         => $reader->readUInt(),
                    'timestamp'  => new \DateTime('@' . $reader->readUInt()),
                    'type'       => $reader->readUInt(),
                    'flags'      => $reader->readUChar(),
                    'bufferInfo' => $reader->readQUserTypeByName('BufferInfo'),
                    'sender'     => $reader->readQByteArray(),
                    'content'    => $reader->readQByteArray()
                );
            },
            'MsgId' => function (Reader $reader) {
                return $reader->readUInt();
            }
        );

        $this->userTypeWriter = array(
            'BufferInfo' => function ($data, Writer $writer) {
                $writer->writeUInt($data['id']);
                $writer->writeUInt($data['network']);
                $writer->writeUShort($data['type']);
                $writer->writeUInt($data['group']);
                $writer->writeQByteArray($data['name']);
            },
            'BufferId' => function ($data, Writer $writer) {
                $writer->writeUInt($data);
            },
            'MsgId' => function ($data, Writer $writer) {
                $writer->writeUInt($data);
            }
        );
    }

    /**
     * Returns whether this instance encode/decodes for the old legacy protcol
     *
     * @return boolean
     */
    public function isLegacy()
    {
        return $this->legacy;
    }

    /**
     * encode the given list of values
     *
     * @param mixed[]|array<mixed> $list
     * @return string binary packet contents
     */
    public function writeVariantList(array $list)
    {
        $writer = new Writer(null, $this->types, $this->userTypeWriter);

        if ($this->isLegacy()) {
            // legacy protocols prefixes list with type information
            $writer->writeQVariant($list);
        } else {
            // datastream protocol just uses list contents
            $writer->writeQVariantList($list);
        }

        return (string)$writer;
    }

    /**
     * encode the given map of key/value-pairs
     *
     * @param mixed[]|array<mixed> $map
     * @return string binary packet contents
     */
    public function writeVariantMap(array $map)
    {
        $writer = new Writer(null, $this->types);

        if ($this->isLegacy()) {
            // legacy protocol prefixes map with type information
            $writer->writeQVariant($map);
        } else {
            // datastream protocol just uses list contents with UTF-8 keys
            // the list always starts with a key string, which can be used to tell apart from actual list contents
            // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L80
            $writer->writeQVariantList($this->mapToList($map));
        }

        return (string)$writer;
    }

    /**
     * encode the given packet data to include framing (packet length)
     *
     * @param string $packet binary packet contents
     * @return string binary packet contents prefixed with frame length
     */
    public function writePacket($packet)
    {
        // TODO: legacy compression / decompression
        // legacy protocol writes variant via DataStream to ByteArray
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L105
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L63
        //$data = $this->types->writeByteArray($data);

        // raw data is prefixed with length, then written
        // https://github.com/quassel/quassel/blob/master/src/common/remotepeer.cpp#L241
        return $this->binary->writeUInt32(strlen($packet)) . $packet;
    }

    /**
     * decodes the given packet contents and returns its representation in PHP
     *
     * @param string $packet bianry packet contents
     * @return mixed[]|array<mixed> list of values or map of key/value-pairs
     */
    public function readVariant($packet)
    {
        $reader = Reader::fromString($packet, $this->types, $this->userTypeReader);

        if ($this->isLegacy()) {
            // legacy protcol always uses type prefix, so just read as variant
            return $reader->readQVariant();
        }

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
            // first 3 elements are unchanged, everything else should be a map
            return array_slice($value, 0, 3) + $this->listToMap(array_slice($value, 3));
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
