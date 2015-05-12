<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Types;
use Clue\QDataStream\Reader;

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

    public function writeVariantList(array $list, $explicitTypes = array())
    {
        $writer = new Writer(null, $this->types, $this->userTypeWriter);
        $writer->writeType(Types::TYPE_QVARIANT_LIST);
        $writer->writeQVariantList($list, $explicitTypes);

        return (string)$writer;
    }

    public function writeVariantMap(array $map)
    {
        // TODO: datastream protocol uses UTF-8 keys..
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L80
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L109

        $writer = new Writer(null, $this->types);
        $writer->writeType(Types::TYPE_QVARIANT_MAP);
        $writer->writeQVariantMap($map);

        return (string)$writer;
    }

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

    public function readVariant($packet)
    {
        $reader = Reader::fromString($packet, $this->types, $this->userTypeReader);

        return $reader->readQVariant();
    }
}
