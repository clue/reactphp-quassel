<?php

namespace Clue\React\Quassel;

use Clue\QDataStream\QVariant;
use Clue\QDataStream\Types;
use Clue\React\Quassel\Io\PacketSplitter;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Models\BufferInfo;
use Evenement\EventEmitter;
use React\Stream\DuplexStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

class Client extends EventEmitter implements DuplexStreamInterface
{
    private $stream;
    private $protocol;
    private $splitter;
    private $closed = false;

    /**
     * [internal] Constructor, see Factory instead
     * @internal
     * @see Factory
     */
    public function __construct(DuplexStreamInterface $stream, Protocol $protocol = null, PacketSplitter $splitter = null)
    {
        if ($protocol === null) {
            $protocol = Protocol::createFromProbe(0);
        }
        if ($splitter === null) {
            $splitter = new PacketSplitter();
        }

        $this->stream = $stream;
        $this->protocol = $protocol;
        $this->splitter = $splitter;

        $stream->on('data', array($this, 'handleData'));
        $stream->on('end', array($this, 'handleEnd'));
        $stream->on('error', array($this, 'handleError'));
        $stream->on('close', array($this, 'close'));
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

    /**
     * Sends a heartbeat request
     *
     * Expect the Quassel IRC core to respond with a heartbeat reply with the
     * same Datetime object with millisecond precision.
     *
     * Giving a DateTime object is optional because the most common use case is
     * to send the current timestamp. It is recommended to not give one so the
     * appropriate timestamp is sent automatically. Otherwise, you should make
     * sure to use a DateTime object with appropriate precision.
     *
     * Note that the Quassel protocol limits the DateTime accuracy to
     * millisecond precision and incoming DateTime objects will always be
     * expressed in the current default timezone. Also note that the legacy
     * protocol only transports number of milliseconds since midnight, so this
     * is not suited a transport arbitrary timestamps.
     *
     * @param null|\DateTime $dt (optional) DateTime object with millisecond precision or null=current timestamp
     * @return bool
     */
    public function writeHeartBeatRequest(\DateTime $dt = null)
    {
        if ($dt === null) {
            $dt = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        }

        return $this->write(array(
            Protocol::REQUEST_HEARTBEAT,
            $dt
        ));
    }

    /**
     * Sends a heartbeat reply
     *
     * This should be sent in response to an incoming heartbeat request from
     * the Quassel IRC core.
     *
     * Giving a DateTime object is mandatory because the most common use case is
     * responding with the same timestamp that was given in the incoming
     * heartbeat request message.
     *
     * @param \DateTime $dt
     * @return boolean
     */
    public function writeHeartBeatReply(\DateTime $dt)
    {
        return $this->write(array(
            Protocol::REQUEST_HEARTBEATREPLY,
            $dt
        ));
    }

    /**
     * Sends a chat message to the given buffer/channel
     *
     * @param BufferInfo $bufferInfo buffer/channel to send to (from previous Message object or SessionInit message)
     * @param string     $contents   buffer input (chat message) to send
     * @return bool
     */
    public function writeBufferInput(BufferInfo $bufferInfo, $contents)
    {
        return $this->write(array(
            Protocol::REQUEST_RPCCALL,
            "2sendInput(BufferInfo,QString)",
            new QVariant($bufferInfo, 'BufferInfo'),
            (string)$contents
        ));
    }

    /**
     * Sends a backlog request for the given buffer/channel
     *
     * If you want to fetch the newest 20 messages for a channel, you can simply
     * pass the correct buffer ID, a $maxAmount of 20 and leave the other
     * parameters unset. This will respond with a message that contains the last
     * 20 messages (if any) where the newest message is the first element in the
     * array of messages.
     *
     * ```php
     * $client->writeBufferRequestBacklog($id, -1, -1, 20, 0);
     * ```
     *
     * If you want to fetch the next 20 older messages for this channel, you
     * can simply pick the message ID of the oldested (and thus last) message
     * in this array and pass this to this method as `$messageIdLast`.
     *
     * ```php
     * $oldest = end($messages)->getId();
     * $client->writeBufferRequestBacklog($id, -1, $oldest, 20, 0);
     * ```
     *
     * If you want to poll the channel for new messages, you can simply pick the
     * message ID of the newest (and thus first) message in the previous array
     * and pass this ID to this method as `$messageIdFirst`. This will return
     * the last 20 messages (if any) and will include the given message ID as
     * the last element in the array of messages if no more than 20 new messages
     * arrived in the meantime. If no new messages are available, this array
     * will contain the given message ID as the only entry.
     *
     * ```php
     * $newest = reset($messages)->getId();
     * $client->writeBufferRequestBacklog($id, $newest, -1, 20, 0);
     * ```
     *
     * @param int $bufferId       buffer/channel to fetch backlog from
     * @param int $messageIdFirst optional, only fetch messages newer than this ID, -1=no limit
     * @param int $messageIdLast  optional, only fetch messages older than this ID, -1=no limit
     * @param int $maxAmount      maximum number of messages to fetch at once, -1=no limit
     * @param int $additional     number of additional messages to fetch, 0=none, -1=no limit
     * @return bool
     * @see self::writeBufferRequestBacklogAll()
     */
    public function writeBufferRequestBacklog($bufferId, $messageIdFirst, $messageIdLast, $maxAmount, $additional)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BacklogManager",
            "",
            "requestBacklog",
            new QVariant((int)$bufferId, 'BufferId'),
            new QVariant((int)$messageIdFirst, 'MsgId'),
            new QVariant((int)$messageIdLast, 'MsgId'),
            (int)$maxAmount,
            (int)$additional
        ));
    }

    /**
     * Sends a backlog request for all messages in all channels
     *
     * @param int $messageIdFirst
     * @param int $messageIdLast
     * @param int $maxAmount
     * @param int $additional
     * @return bool
     * @see self::writeBufferRequestBacklog() for parameter description
     */
    public function writeBufferRequestBacklogAll($messageIdFirst, $messageIdLast, $maxAmount, $additional)
    {
        return $this->write(array(
            Protocol::REQUEST_SYNC,
            "BacklogManager",
            "",
            "requestBacklogAll",
            new QVariant((int)$messageIdFirst, 'MsgId'),
            new QVariant((int)$messageIdLast, 'MsgId'),
            (int)$maxAmount,
            (int)$additional
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
        return $this->stream->write(
            $this->splitter->writePacket(
                $this->protocol->serializeVariantPacket($data)
            )
        );
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->stream->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->stream->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleData($chunk)
    {
        // chunk of packet data received
        // feed chunk to splitter, which will invoke a callable for each complete packet
        try {
            $this->splitter->push($chunk, array($this, 'handlePacket'));
        } catch (\OverflowException $e) {
            $this->handleError($e);
        }
    }

    /** @internal */
    public function handlePacket($packet)
    {
        // complete packet data received
        // parse variant data from binary packet and forward as data event
        $this->emit('data', array($this->protocol->parseVariantPacket($packet)));
    }

    /** @internal */
    public function handleEnd()
    {
        if ($this->splitter->isEmpty()) {
            $this->emit('end');
            $this->close();
        } else {
            $this->handleError(new \RuntimeException('Connection ended while receiving data'));
        }
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleDrain()
    {
        $this->emit('drain');
    }
}
