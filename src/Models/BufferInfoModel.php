<?php

namespace Clue\React\Quassel\Models;

class BufferInfoModel
{
    private $id;
    private $networkId;
    private $type;
    private $groupId;
    private $name;

    /**
     * @param int    $id
     * @param int    $networkId
     * @param int    $type
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
     * @return int
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
