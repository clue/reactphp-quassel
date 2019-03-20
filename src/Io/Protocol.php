<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Reader;
use Clue\React\Quassel\Models\BufferInfo;
use Clue\React\Quassel\Models\Message;

/** @internal */
abstract class Protocol
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

    protected $userTypeReader;
    protected $userTypeWriter;

    public static function createFromProbe($probe)
    {
        if ($probe & self::TYPE_DATASTREAM) {
            return new DatastreamProtocol();
        } else {
            return new LegacyProtocol();
        }
    }

    public function __construct()
    {
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
                return new BufferInfo(
                    $reader->readUInt(),
                    $reader->readUInt(),
                    $reader->readUShort(),
                    $reader->readUInt(),
                    $reader->readQByteArray()
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
                $readTimestamp = function () use ($reader) {
                    // create DateTime object with local time zone from unix timestamp
                    $d = new \DateTime('@' . $reader->readUint());
                    $d->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
                    return $d;
                };

                return new Message(
                    $reader->readUInt(),
                    $readTimestamp(),
                    $reader->readUInt(),
                    $reader->readUChar(),
                    $reader->readQUserTypeByName('BufferInfo'),
                    $reader->readQByteArray(),
                    $reader->readQByteArray()
                );
            },
            'MsgId' => function (Reader $reader) {
                return $reader->readUInt();
            }
        );

        $this->userTypeWriter = array(
            'BufferInfo' => function (BufferInfo $buffer, Writer $writer) {
                $writer->writeUInt($buffer->id);
                $writer->writeUInt($buffer->networkId);
                $writer->writeUShort($buffer->type);
                $writer->writeUInt($buffer->groupId);
                $writer->writeQByteArray($buffer->name);
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
    abstract public function isLegacy();

    /**
     * encode the given list of values or map of key/value pairs to a binary packet
     *
     * @param mixed[]|array<mixed> $data
     * @return string binary packet contents
     */
    abstract public function serializeVariantPacket(array $data);

    /**
     * decodes the given packet contents and returns its representation in PHP
     *
     * @param string $packet binary packet contents
     * @return mixed[]|array<mixed> list of values or map of key/value-pairs
     */
    abstract public function parseVariantPacket($packet);
}
