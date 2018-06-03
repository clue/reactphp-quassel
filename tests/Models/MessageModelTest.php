<?php

use Clue\React\Quassel\Models\MessageModel;

class MessageModelTest extends TestCase
{
    public function testChatMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfoModel')->disableOriginalConstructor()->getMock();
        $message = new MessageModel(
            1000,
            1528039705,
            MessageModel::TYPE_PLAIN,
            MessageModel::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            'Hello world!'
        );

        $this->assertSame(1000, $message->getId());
        $this->assertSame(1528039705, $message->getTimestamp());
        $this->assertSame(MessageModel::TYPE_PLAIN, $message->getType());
        $this->assertSame(MessageModel::FLAG_NONE, $message->getFlags());
        $this->assertSame($buffer, $message->getBufferInfo());
        $this->assertSame('another_clue!user@host', $message->getSender());
        $this->assertSame('Hello world!', $message->getContents());
    }

    public function testJoinMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfoModel')->disableOriginalConstructor()->getMock();
        $message = new MessageModel(
            999,
            1528039704,
            MessageModel::TYPE_JOIN,
            MessageModel::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            '#reactphp'
        );

        $this->assertSame(999, $message->getId());
        $this->assertSame(1528039704, $message->getTimestamp());
        $this->assertSame(MessageModel::TYPE_JOIN, $message->getType());
        $this->assertSame(MessageModel::FLAG_NONE, $message->getFlags());
        $this->assertSame($buffer, $message->getBufferInfo());
        $this->assertSame('another_clue!user@host', $message->getSender());
        $this->assertSame('#reactphp', $message->getContents());
    }
}
