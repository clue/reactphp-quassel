<?php

namespace Clue\React\Quassel;

use React\Stream\Stream;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Io\PacketSplitter;
use Clue\React\Quassel\Io\Binary;
use Evenement\EventEmitter;
use Clue\QDataStream\Types;
use Clue\QDataStream\QVariant;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use React\Stream\DuplexStreamInterface;

class Client extends EventEmitter implements DuplexStreamInterface
{
    private $stream;
    private $protocol;
    private $splitter;

    public function __construct(Stream $stream, Protocol $protocol = null, PacketSplitter $splitter = null)
    {
        if ($protocol === null) {
            $protocol = Protocol::createFromProbe(0);
        }
        if ($splitter === null) {
            $splitter = new PacketSplitter(new Binary());
        }

        $this->stream = $stream;
        $this->protocol = $protocol;
        $this->splitter = $splitter;

        $stream->on('data', array($this, 'handleData'));
        $stream->on('end', array($this, 'handleEnd'));
        $stream->on('error', array($this, 'handleError'));
        $stream->on('close', array($this, 'handleClose'));
        $stream->on('drain', array($this, 'handleDrain'));
    }

    /**
     * send client init info
     *
     * expect either of ClientInitAck or ClientInitReject["Error"] in response
     *
     * ClientInitAck["Configured"] === true means you should continue with
     * writeClientInit() next
     *
     * ClientInitAck["Configured"] === false means you should continue with
     * writeCoreSetupData() next
     *
     * @param boolean $compression (only for legacy protocol)
     * @param boolean $ssl         (only for legacy protocol)
     * @return boolean
     */
    public function writeClientInit($compression = false, $ssl = false)
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

        return $this->write($data);
    }

    /**
     * send client login credentials
     *
     * expect either of ClientLoginAck or ClientLoginReject["Error"] in response
     *
     * @param string $user
     * @param string $password
     * @return boolean
     */
    public function writeClientLogin($user, $password)
    {
        return $this->write(array(
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
     * @return boolean
     */
    public function writeCoreSetupData($user, $password, $backend = 'SQLite', $properties = array())
    {
        return $this->write(array(
            'MsgType' => 'CoreSetupData',
            'SetupData' => array(
                'AdminUser' => (string)$user,
                'AdminPasswd' => (string)$password,
                'Backend' => (string)$backend,
                'ConnectionProperties' => $properties
            )
        ));
    }

    public function writeInitRequest($class, $name)
    {
        return $this->write(array(
            Protocol::REQUEST_INITREQUEST,
            (string)$class,
            (string)$name
        ));
    }

    public function writeHeartBeatRequest(\DateTime $dt)
    {
        return $this->write(array(
            Protocol::REQUEST_HEARTBEAT,
            $this->createQVariantDateTime($dt)
        ));
    }

    public function writeHeartBeatReply(\DateTime $dt)
    {
        return $this->write(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            $this->createQVariantDateTime($dt)
        ));
    }

    public function writeBufferInput($bufferInfo, $input)
    {
        return $this->write(array(
            Protocol::REQUEST_RPCCALL,
            "2sendInput(BufferInfo,QString)",
            new QVariant($bufferInfo, 'BufferInfo'),
            (string)$input
        ));
    }

    public function writeBufferRequestBacklog($bufferId, $maxAmount, $messageIdFirst = -1, $messageIdLast = -1)
    {
        return $this->write(array(
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

    public function writeBufferRequestRemove($bufferId)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestRemoveBuffer", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId')
        ));
    }

    public function writeBufferRequestMarkAsRead($bufferId)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestMarkBufferAsRead", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId')
        ));
    }

    public function writeBufferRequestSetLastSeenMessage($bufferId, $messageId)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestSetLastSeenMsg", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId'),
            new QVariant($messageId, 'MsgId')
        ));
    }

    public function writeBufferRequestSetMarkerLine($bufferId, $messageId)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BufferSyncer",
            "",
            new QVariant("requestSetMarkerLine", Types::TYPE_QBYTE_ARRAY),
            new QVariant($bufferId, 'BufferId'),
            new QVariant($messageId, 'MsgId')
        ));
    }

    public function writeNetworkRequestConnect($networkId)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "Network",
            (string)$networkId,
            new QVariant("requestConnect", Types::TYPE_QBYTE_ARRAY)
        ));
    }

    public function writeNetworkRequestDisconnect($networkId)
    {
        return $this->write(array(
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

    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function pause()
    {
        $this->stream->pause();
    }

    public function resume()
    {
        $this->stream->resume();
    }

    /**
     * writes the given data array to the underlying connection
     *
     * This is a low level method that should only be used if you know what
     * you're doing. Also check the other write*() methods instead.
     *
     * @param array $data
     * @return boolean returns boolean false if buffer is full and writing should be throttled
     */
    public function write($data)
    {
        if (isset($data[0])) {
            $packet = $this->protocol->writeVariantList($data);
        } else {
            $packet = $this->protocol->writeVariantMap($data);
        }

        return $this->stream->write($this->splitter->writePacket($packet));
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->stream->end();
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
        $this->emit('data', array($data));
    }

    /** @internal */
    public function handleEnd()
    {
        $this->emit('end');
        $this->close();
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleClose()
    {
        $this->emit('close');
    }

    /** @internal */
    public function handleDrain()
    {
        $this->emit('drain');
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
