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
            0x01,
            0x00,
            $buffer,
            'another_clue!user@host',
            'Hello world!'
        );

        $this->assertSame(1000, $message->getId());
        $this->assertSame(1528039705, $message->getTimestamp());
        $this->assertSame(0x01, $message->getType());
        $this->assertSame(0x00, $message->getFlags());
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
            0x20,
            0x00,
            $buffer,
            'another_clue!user@host',
            '#reactphp'
        );

        $this->assertSame(999, $message->getId());
        $this->assertSame(1528039704, $message->getTimestamp());
        $this->assertSame(0x20, $message->getType());
        $this->assertSame(0x00, $message->getFlags());
        $this->assertSame($buffer, $message->getBufferInfo());
        $this->assertSame('another_clue!user@host', $message->getSender());
        $this->assertSame('#reactphp', $message->getContents());
    }
}
