<?php

use Clue\React\Quassel\Models\Message;

class MessageTest extends TestCase
{
    public function testChatMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfo')->disableOriginalConstructor()->getMock();
        $message = new Message(
            1000,
            1528039705,
            Message::TYPE_PLAIN,
            Message::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            'Hello world!'
        );

        $this->assertSame(1000, $message->getId());
        $this->assertSame(1528039705, $message->getTimestamp());
        $this->assertSame(Message::TYPE_PLAIN, $message->getType());
        $this->assertSame(Message::FLAG_NONE, $message->getFlags());
        $this->assertSame($buffer, $message->getBufferInfo());
        $this->assertSame('another_clue!user@host', $message->getSender());
        $this->assertSame('Hello world!', $message->getContents());
    }

    public function testJoinMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfo')->disableOriginalConstructor()->getMock();
        $message = new Message(
            999,
            1528039704,
            Message::TYPE_JOIN,
            Message::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            '#reactphp'
        );

        $this->assertSame(999, $message->getId());
        $this->assertSame(1528039704, $message->getTimestamp());
        $this->assertSame(Message::TYPE_JOIN, $message->getType());
        $this->assertSame(Message::FLAG_NONE, $message->getFlags());
        $this->assertSame($buffer, $message->getBufferInfo());
        $this->assertSame('another_clue!user@host', $message->getSender());
        $this->assertSame('#reactphp', $message->getContents());
    }
}
