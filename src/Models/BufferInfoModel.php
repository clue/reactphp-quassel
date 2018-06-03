<?php

namespace Clue\React\Quassel\Models;

class BufferInfoModel
{
    // @link https://github.com/quassel/quassel/blob/e17fca767d60c06ca02bc5898ced04f06d3670bd/src/common/bufferinfo.h#L32
    const TYPE_INVALID = 0x00;
    const TYPE_STATUS = 0x01;
    const TYPE_CHANNEL = 0x02;
    const TYPE_QUERY = 0x04;
    const TYPE_GROUP = 0x08;

    private $id;
    private $networkId;
    private $type;
    private $groupId;
    private $name;

    /**
     * @param int    $id
     * @param int    $networkId
     * @param int    $type      single type constant, see self::TYPE_*
     * @param int    $groupId
     * @param string $name      buffer/channel name `#channel`, `user` or empty string
     */
    public function __construct($id, $networkId, $type, $groupId, $name)
    {
        $this->id = $id;
        $this->networkId = $networkId;
        $this->type = $type;
        $this->groupId = $groupId;
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getNetworkId()
    {
        return $this->networkId;
    }

    /**
     * @return int single type constant, see self::TYPE_*
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getGroupId()
    {
        return $this->groupId;
    }

    /**
     * @return string buffer/channel name `#channel` or `user` or empty string
     */
    public function getName()
    {
        return $this->name;
    }
}
