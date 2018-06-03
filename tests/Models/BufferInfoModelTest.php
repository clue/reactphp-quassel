<?php

use Clue\React\Quassel\Models\BufferInfoModel;

class BufferInfoModelTest extends TestCase
{
    public function testBufferInfoForNormalChannel()
    {
        $model = new BufferInfoModel(1, 3, 0x02, 0, '#reactphp');

        $this->assertSame(1, $model->getId());
        $this->assertSame(3, $model->getNetworkId());
        $this->assertSame(0x02, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('#reactphp', $model->getName());
    }

    public function testBufferInfoForUserQuery()
    {
        $model = new BufferInfoModel(2, 1, 0x04, 0, 'another_clue');

        $this->assertSame(2, $model->getId());
        $this->assertSame(1, $model->getNetworkId());
        $this->assertSame(0x04, $model->getType());
        $this->assertSame(0, $model->getGroupId());
        $this->assertSame('another_clue', $model->getName());
    }
}
