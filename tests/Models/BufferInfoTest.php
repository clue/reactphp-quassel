<?php

use Clue\React\Quassel\Models\BufferInfo;

class BufferInfoTest extends TestCase
{
    public function testBufferInfoForNormalChannel()
    {
        $model = new BufferInfo(1, 3, BufferInfo::TYPE_CHANNEL, 0, '#reactphp');

        $this->assertSame(1, $model->getId());
        $this->assertSame(3, $model->getNetworkId());
        $this->assertSame(BufferInfo::TYPE_CHANNEL, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('#reactphp', $model->getName());
    }

    public function testBufferInfoForUserQuery()
    {
        $model = new BufferInfo(2, 1, BufferInfo::TYPE_QUERY, 0, 'another_clue');

        $this->assertSame(2, $model->getId());
        $this->assertSame(1, $model->getNetworkId());
        $this->assertSame(BufferInfo::TYPE_QUERY, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('another_clue', $model->getName());
    }
}
