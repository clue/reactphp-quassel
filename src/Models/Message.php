<?php

namespace Clue\React\Quassel\Models;

class Message
{
    // @link https://github.com/quassel/quassel/blob/e17fca767d60c06ca02bc5898ced04f06d3670bd/src/common/message.h#L35
    const TYPE_PLAIN = 0x00001;
    const TYPE_NOTICE = 0x00002;
    const TYPE_ACTION = 0x00004;
    const TYPE_NICK = 0x00008;
    const TYPE_MODE = 0x00010;
    const TYPE_JOIN = 0x00020;
    const TYPE_PART = 0x00040;
    const TYPE_QUIT = 0x00080;
    const TYPE_KICK = 0x00100;
    const TYPE_KILL = 0x00200;
    const TYPE_SERVER = 0x00400;
    const TYPE_INFO = 0x00800;
    const TYPE_ERROR = 0x01000;
    const TYPE_DAY_CHANGE = 0x02000;
    const TYPE_TOPIC = 0x04000;
    const TYPE_NETSPLIT_JOIN = 0x08000;
    const TYPE_NETSPLIT_QUIT = 0x10000;
    const TYPE_INVITE = 0x20000;

    // @link https://github.com/quassel/quassel/blob/e17fca767d60c06ca02bc5898ced04f06d3670bd/src/common/message.h#L59
    const FLAG_NONE = 0x00;
    const FLAG_SELF = 0x01;
    const FLAG_HIGHLIGHT = 0x02;
    const FLAG_REDIRECTED = 0x04;
    const FLAG_SERVER_MESSAGE = 0x08;
    const FLAG_STATUS_MESSAGE = 0x10;
    const FLAG_BACKLOG = 0x80;

    /**
     * @var int
     */
    public $id;

    /**
     * @var \DateTime
     */
    public $timestamp;

    /**
     * @var int single type constant, see self::TYPE_* constants
     */
    public $type;

    /**
     * @var int bitmask of flag constants, see self::FLAG_* constants
     */
    public $flags;

    /**
     * @var BufferInfo reference to the buffer/channel this message was received in
     */
    public $bufferInfo;

    /**
     * @var string `nick!user@host` or just host or empty string depending on type/flags
     */
    public $sender;

    /**
     * @var string message contents contains the chat message or info which may be empty depending on type
     */
    public $contents;

    /**
     * [Internal] Instantiation is handled internally and should not be called manually.
     *
     * @param int        $id
     * @param \DateTime  $timestamp
     * @param int        $type       single type constant, see self::TYPE_* constants
     * @param int        $flags      bitmask of flag constants, see self::FLAG_* constants
     * @param BufferInfo $bufferInfo
     * @param string     $sender     sender in the form `nick!user@host` or only `host` or empty string
     * @param string     $contents
     * @internal
     */
    public function __construct($id, \DateTime $timestamp, $type, $flags, BufferInfo $bufferInfo, $sender, $contents)
    {
        $this->id = $id;
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->flags = $flags;
        $this->bufferInfo = $bufferInfo;
        $this->sender = $sender;
        $this->contents = $contents;
    }
}
