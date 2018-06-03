<?php

namespace Clue\React\Quassel\Models;

class MessageModel
{
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
     * @param int             $type
     * @param int             $flags
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
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
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
