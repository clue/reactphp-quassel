<?php

namespace Clue\React\Quassel\Models;

class MessageModel
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

    private $id;
    private $timestamp;
    private $type;
    private $flags;
    private $bufferInfo;
    private $sender;
    private $content;

    /**
     *
     * @param int             $id
     * @param int             $timestamp  UNIX timestamp
     * @param int             $type       single type constant, see self::TYPE_* constants
     * @param int             $flags      bitmask of flag constants, see self::FLAG_* constants
     * @param BufferInfoModel $bufferInfo
     * @param string          $sender     sender in the form `nick!user@host` or only `host` or empty string
     * @param string          $content
     */
    public function __construct($id, $timestamp, $type, $flags, BufferInfoModel $bufferInfo, $sender, $content)
    {
        $this->id = $id;
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->flags = $flags;
        $this->bufferInfo = $bufferInfo;
        $this->sender = $sender;
        $this->content = $content;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int UNIX timestamp
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return int single type constant, see self::TYPE_* constants
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int bitmask of flag constants, see self::FLAG_* constants
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @return BufferInfoModel
     */
    public function getBufferInfo()
    {
        return $this->bufferInfo;
    }

    /**
     * @return string `nick!user@host` or just host or empty string depending on type/flags
     * @see self::getSenderNick()
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * @return string message contents contains the chat message or info which may be empty depending on type
     */
    public function getContents()
    {
        return $this->content;
    }
}
