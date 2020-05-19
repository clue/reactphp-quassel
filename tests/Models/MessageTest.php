<?php

namespace Clue\Tests\React\Quassel\Models;

use Clue\React\Quassel\Models\Message;
use Clue\Tests\React\Quassel\TestCase;

class MessageTest extends TestCase
{
    public function testChatMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfo')->disableOriginalConstructor()->getMock();
        $message = new Message(
            1000,
            new \DateTime('@1528039705'),
            Message::TYPE_PLAIN,
            Message::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            'Hello world!'
        );

        $this->assertSame(1000, $message->id);
        $this->assertSame(1528039705, $message->timestamp->getTimestamp());
        $this->assertSame(Message::TYPE_PLAIN, $message->type);
        $this->assertSame(Message::FLAG_NONE, $message->flags);
        $this->assertSame($buffer, $message->bufferInfo);
        $this->assertSame('another_clue!user@host', $message->sender);
        $this->assertSame('Hello world!', $message->contents);
    }

    public function testJoinMessage()
    {
        $buffer = $this->getMockBuilder('Clue\React\Quassel\Models\BufferInfo')->disableOriginalConstructor()->getMock();
        $message = new Message(
            999,
            new \DateTime('@1528039704'),
            Message::TYPE_JOIN,
            Message::FLAG_NONE,
            $buffer,
            'another_clue!user@host',
            '#reactphp'
        );

        $this->assertSame(999, $message->id);
        $this->assertSame(1528039704, $message->timestamp->getTimestamp());
        $this->assertSame(Message::TYPE_JOIN, $message->type);
        $this->assertSame(Message::FLAG_NONE, $message->flags);
        $this->assertSame($buffer, $message->bufferInfo);
        $this->assertSame('another_clue!user@host', $message->sender);
        $this->assertSame('#reactphp', $message->contents);
    }
}
