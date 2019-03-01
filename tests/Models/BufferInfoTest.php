<?php

use Clue\React\Quassel\Models\BufferInfo;

class BufferInfoTest extends TestCase
{
    public function testBufferInfoForNormalChannel()
    {
        $model = new BufferInfo(1, 3, BufferInfo::TYPE_CHANNEL, 0, '#reactphp');

        $this->assertSame(1, $model->id);
        $this->assertSame(3, $model->networkId);
        $this->assertSame(BufferInfo::TYPE_CHANNEL, $model->type);
        $this->assertSame(0, $model->groupId);
        $this->assertSame('#reactphp', $model->name);
    }

    public function testBufferInfoForUserQuery()
    {
        $model = new BufferInfo(2, 1, BufferInfo::TYPE_QUERY, 0, 'another_clue');

        $this->assertSame(2, $model->id);
        $this->assertSame(1, $model->networkId);
        $this->assertSame(BufferInfo::TYPE_QUERY, $model->type);
        $this->assertSame(0, $model->groupId);
        $this->assertSame('another_clue', $model->name);
    }
}
