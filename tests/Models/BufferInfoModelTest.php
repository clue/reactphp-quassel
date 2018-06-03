<?php

use Clue\React\Quassel\Models\BufferInfoModel;

class BufferInfoModelTest extends TestCase
{
    public function testBufferInfoForNormalChannel()
    {
        $model = new BufferInfoModel(1, 3, BufferInfoModel::TYPE_CHANNEL, 0, '#reactphp');

        $this->assertSame(1, $model->getId());
        $this->assertSame(3, $model->getNetworkId());
        $this->assertSame(BufferInfoModel::TYPE_CHANNEL, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('#reactphp', $model->getName());
    }

    public function testBufferInfoForUserQuery()
    {
        $model = new BufferInfoModel(2, 1, BufferInfoModel::TYPE_QUERY, 0, 'another_clue');

        $this->assertSame(2, $model->getId());
        $this->assertSame(1, $model->getNetworkId());
        $this->assertSame(BufferInfoModel::TYPE_QUERY, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('another_clue', $model->getName());
    }
}
