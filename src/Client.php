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

class Client extends EventEmitter
{
    private $stream;
    private $protocol;
    private $splitter;

    public function __construct(Stream $stream, Protocol $protocol = null, PacketSplitter $splitter = null)
    {
        if ($protocol === null) {
            $protocol = new Protocol(new Binary());
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
     * @param boolean $compression
     * @param boolean $ssl
     */
    public function sendClientInit($compression = false, $ssl = false)
    {
        // MMM dd yyyy HH:mm:ss
        $date = date('M d Y H:i:s');

        $this->send($this->protocol->writeVariantMap(array(
            'MsgType' => 'ClientInit',
            'ClientDate' => $date,
            'ClientVersion' => 'clue/quassel-react alpha',
            'ProtocolVersion' => 10,
            'UseCompression' => (bool)$compression,
            'UseSsl' => (bool)$ssl
        )));
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
        $this->send($this->protocol->writeVariantMap(array(
            'MsgType' => 'ClientLogin',
            'User' => (string)$user,
            'Password' => (string)$password
        )));
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
        $this->send($this->protocol->writeVariantMap(array(
            'MsgType' => 'CoreSetupData',
            'SetupData' => array(
                'AdminUser' => (string)$user,
                'AdminPasswd' => (string)$password,
                'Backend' => (string)$backend,
                'ConnectionProperties' => $properties
            )
        )));
    }

    public function sendInitRequest($class, $name)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_INITREQUEST,
            (string)$class,
            (string)$name
        )));
    }

    public function sendHeartBeatRequest(\DateTime $dt)
    {
        // TODO: legacy protocol uses a QTime, datastream protocol QDateTime
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_HEARTBEAT,
            $dt
        ), array(1 => Types::TYPE_QTIME)));
    }

    public function sendHeartBeatReply(\DateTime $dt)
    {
        // TODO: legacy protocol uses a QTime, datastream protocol QDateTime
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            $dt
        ), array(1 => Types::TYPE_QTIME)));
    }

    public function sendBufferInput($bufferInfo, $input)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_RPCCALL,
            "2sendInput(BufferInfo,QString)",
            $bufferInfo,
            (string)$input
        ), array(2 => 'BufferInfo')));
    }

    public function sendBufferRequestBacklog($bufferId, $maxAmount, $messageIdFirst = -1, $messageIdLast = -1)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "BacklogManager",
            "",
            "requestBacklog",
            (int)$bufferId,
            (int)$messageIdFirst,
            (int)$messageIdLast,
            (int)$maxAmount,
            0
        ), array(4 => 'BufferId', 5 => 'MsgId', 6 => 'MsgId')));
    }

    public function sendBufferRequestRemove($bufferId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            "requestRemoveBuffer",
            $bufferId
        ), array(3 => Types::TYPE_QBYTE_ARRAY, 4 => 'BufferId')));
    }

    public function sendBufferRequestMarkAsRead($bufferId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            "requestMarkBufferAsRead",
            $bufferId
        ), array(3 => Types::TYPE_QBYTE_ARRAY, 4 => 'BufferId')));
    }

    public function sendBufferRequestSetLastSeenMessage($bufferId, $messageId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            "requestSetLastSeenMsg",
            $bufferId,
            $messageId
        ), array(3 => Types::TYPE_QBYTE_ARRAY, 4 => 'BufferId', 5 => 'MsgId')));
    }

    public function sendBufferRequestSetMarkerLine($bufferId, $messageId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            "requestSetMarkerLine",
            $bufferId,
            $messageId
        ), array(3 => Types::TYPE_QBYTE_ARRAY, 4 => 'BufferId', 5 => 'MsgId')));
    }

    public function sendNetworkRequestConnect($networkId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "Network",
            (string)$networkId,
            "requestConnect"
        ), array(3 => Types::TYPE_QBYTE_ARRAY)));
    }

    public function sendNetworkRequestDisconnect($networkId)
    {
        $this->send($this->protocol->writeVariantList(array(
            Protocol::REQUEST_SYNC,
            "Network",
            (string)$networkId,
            "requestDisconnect"
        ), array(3 => Types::TYPE_QBYTE_ARRAY)));
    }

    public function close()
    {
        $this->stream->close();
    }

    /** @internal */
    public function handleData($data)
    {
        //var_dump('received ' . strlen($data) . ' bytes');
        //$h = new Hexdump();
        //echo $h->dump($data);

        // chunk of packet data received
        // feed chunk to splitter, which will invoke a callable for each complete packet
        $this->splitter->push($data, array($this, 'handlePacket'));
    }

    /** @internal */
    public function handlePacket($data)
    {
        // complete packet data received
        // read variant from packet data and forward as message
        $variant = $this->protocol->readVariant($data);
        $this->emit('message', array($variant, $this));
    }

    /** @internal */
    public function handleClose()
    {
        $this->emit('close', array($this));
    }

    private function send($data)
    {
        $this->stream->write($this->protocol->writePacket($data));
    }
}
