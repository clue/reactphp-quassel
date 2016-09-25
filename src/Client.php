<?php

namespace Clue\React\Quassel;

use React\Stream\Stream;
use Clue\Hexdump\Hexdump;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Io\PacketSplitter;
use Clue\React\Quassel\Io\Binary;
use Evenement\EventEmitter;
use React\Promise\Deferred;
use Clue\QDataStream\Types;
use Clue\QDataStream\QVariant;

class Client extends EventEmitter
{
    private $stream;
    private $protocol;
    private $splitter;

    public function __construct(Stream $stream, Protocol $protocol = null, PacketSplitter $splitter = null)
    {
        if ($protocol === null) {
            $protocol = Protocol::createFromProbe(0);;
        }
        if ($splitter === null) {
            $splitter = new PacketSplitter(new Binary());
        }

        $this->stream = $stream;
        $this->protocol = $protocol;
        $this->splitter = $splitter;

        $stream->on('data', array($this, 'handleData'));
        $stream->on('close', array($this, 'handleClose'));
    }

    /**
     * send client init info
     *
     * expect either of ClientInitAck or ClientInitReject["Error"] in response
     *
     * ClientInitAck["Configured"] === true means you should continue with
     * sendClientInit() next
     *
     * ClientInitAck["Configured"] === false means you should continue with
     * sendCoreSetupData() next
     *
     * @param boolean $compression (only for legacy protocol)
     * @param boolean $ssl         (only for legacy protocol)
     */
    public function sendClientInit($compression = false, $ssl = false)
    {
        // MMM dd yyyy HH:mm:ss
        $date = date('M d Y H:i:s');

        $data = array(
            'MsgType' => 'ClientInit',
            'ClientDate' => $date,
            'ClientVersion' => 'clue/quassel-react alpha'
        );

        if ($this->protocol->isLegacy()) {
            $data += array(
                'ProtocolVersion' => 10,
                'UseCompression' => (bool)$compression,
                'UseSsl' => (bool)$ssl
            );
        }

        $this->sendMap($data);
    }

    /**
     * send client login credentials
     *
     * expect either of ClientLoginAck or ClientLoginReject["Error"] in response
     *
     * @param string $user
     * @param string $password
     */
    public function sendClientLogin($user, $password)
    {
        $this->sendMap(array(
            'MsgType' => 'ClientLogin',
            'User' => (string)$user,
            'Password' => (string)$password
        ));
    }

    /**
     * send setup data
     *
     * expect either of CoreSetupAck or CoreSetupReject["Error"] in response
     *
     * Possible values for the $backend and $properties parameters are reported
     * as part of the ClientInitAck["StorageBackends"] list of maps.
     *
     * At the time of writing this, the only supported backends are the default
     * "SQLite" which requires no additional configuration and the significantly
     * faster "PostgreSQL" which accepts a map with your database credentials.
     *
     * @param string $user       admin user name
     * @param string $password   admin password
     * @param string $backend    One of the available "DisplayName" values from ClientInitAck["StorageBackends"]
     * @param array  $properties (optional) map with keys from "SetupKeys" from ClientInitAck["StorageBackends"], where missing keys default to those from the "SetupDefaults"
     */
    public function sendCoreSetupData($user, $password, $backend = 'SQLite', $properties = array())
    {
        $this->sendMap(array(
            'MsgType' => 'CoreSetupData',
            'SetupData' => array(
                'AdminUser' => (string)$user,
                'AdminPasswd' => (string)$password,
                'Backend' => (string)$backend,
                'ConnectionProperties' => $properties
            )
        ));
    }

    public function sendInitRequest($class, $name)
    {
        $this->sendList(array(
            Protocol::REQUEST_INITREQUEST,
            (string)$class,
            (string)$name
        ));
    }

    public function sendHeartBeatRequest(\DateTime $dt)
    {
        $this->sendList(array(
            Protocol::REQUEST_HEARTBEAT,
            $this->createQVariantDateTime($dt)
        ));
    }

    public function sendHeartBeatReply(\DateTime $dt)
    {
        $this->sendList(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            $this->createQVariantDateTime($dt)
        ));
    }

    public function sendBufferInput($bufferInfo, $input)
    {
        $this->sendList(array(
            Protocol::REQUEST_RPCCALL,
            "2sendInput(BufferInfo,QString)",
            new QVariant($bufferInfo, 'BufferInfo'),
            (string)$input
        ));
    }

    public function sendBufferRequestBacklog($bufferId, $maxAmount, $messageIdFirst = -1, $messageIdLast = -1)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "BacklogManager",
            "",
            "requestBacklog",
            new QVariant($bufferId, 'BufferId'),
            new QVariant($messageIdFirst, 'MsgId'),
            new QVariant($messageIdLast, 'MsgId'),
            (int)$maxAmount,
            0
        ));
    }

    public function sendBufferRequestRemove($bufferId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestRemoveBuffer", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId')
        ));
    }

    public function sendBufferRequestMarkAsRead($bufferId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestMarkBufferAsRead", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId')
        ));
    }

    public function sendBufferRequestSetLastSeenMessage($bufferId, $messageId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestSetLastSeenMsg", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId'),
            new QVariant($messageId, 'MsgId')
        ));
    }

    public function sendBufferRequestSetMarkerLine($bufferId, $messageId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestSetMarkerLine", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId'),
            new QVariant($messageId, 'MsgId')
        ));
    }

    public function sendNetworkRequestConnect($networkId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "Network",
            (string)$networkId,
            new QVariant("requestConnect", Types::TYPE_QBYTE_ARRAY)
        ));
    }

    public function sendNetworkRequestDisconnect($networkId)
    {
        $this->sendList(array(
            Protocol::REQUEST_SYNC,
            "Network",
            (string)$networkId,
            new QVariant("requestDisconnect", Types::TYPE_QBYTE_ARRAY)
        ));
    }

    public function close()
    {
        $this->stream->close();
    }

    /** @internal */
    public function handleData($chunk)
    {
        // chunk of packet data received
        // feed chunk to splitter, which will invoke a callable for each complete packet
        $this->splitter->push($chunk, array($this, 'handlePacket'));
    }

    /** @internal */
    public function handlePacket($packet)
    {
        // complete packet data received
        // read variant from packet data and forward as message
        $data = $this->protocol->readVariant($packet);
        $this->emit('message', array($data, $this));
    }

    /** @internal */
    public function handleClose()
    {
        $this->emit('close', array($this));
    }

    private function sendList($data)
    {
        $this->stream->write($this->splitter->writePacket(
            $this->protocol->writeVariantList($data)
        ));
    }

    private function sendMap($data)
    {
        $this->stream->write($this->splitter->writePacket(
            $this->protocol->writeVariantMap($data)
        ));
    }

    private function createQVariantDateTime(\DateTime $dt)
    {
        // The legacy protocol uses QTime which does not obey timezones or DST
        // properties. Instead, convert everything to UTC so we send absolute
        // timestamps irrespective of actual timezone.
        if ($this->protocol->isLegacy()) {
            $dt = clone $dt;
            $dt->setTimeZone(new \DateTimeZone('UTC'));
        }

        // legacy protocol uses limited QTime while newer datagram protocol uses proper QDateTime
        return new QVariant($dt, $this->protocol->isLegacy() ? Types::TYPE_QTIME : Types::TYPE_QDATETIME);
    }
}
