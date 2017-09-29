<?php

namespace Clue\React\Quassel\Io;

use React\Stream\DuplexStreamInterface;
use React\Promise\Deferred;

/** @internal */
class Prober
{
    const ERROR_PROTOCOL = 4;
    const ERROR_CLOSED = 3;

    private $binary;

    public function __construct(Binary $binary = null)
    {
        if ($binary === null) {
            $binary = new Binary();
        }
        $this->binary = $binary;
    }

    public function probe(DuplexStreamInterface $stream, $compression = false, $encryption = false)
    {
        $magic = Protocol::MAGIC;
        if ($compression) {
            $magic |= Protocol::FEATURE_COMPRESSION;
        }
        if ($encryption) {
            $magic |= Protocol::FEATURE_ENCRYPTION;
        }

        $binary = $this->binary;

        $stream->write($binary->writeUInt32($magic));

        // list of supported protocol types (in order of preference)
        $types = array(Protocol::TYPE_DATASTREAM, Protocol::TYPE_LEGACY);

        // last item should get an END marker
        $last = array_pop($types);
        $types []= $last | Protocol::TYPELIST_END;

        foreach ($types as $type) {
            $stream->write($binary->writeUInt32($type));
        }

        $deferred = new Deferred(function ($resolve, $reject) use ($stream) {
            $reject(new \RuntimeException('Cancelled'));
        });

        $buffer = '';
        $fn = function ($data) use (&$buffer, &$fn, $stream, $deferred, $binary) {
            $buffer .= $data;

            if (isset($buffer[4])) {
                $stream->removeListener('data', $fn);
                $deferred->reject(new \UnexpectedValueException('Expected 4 bytes response, received more data, is this a quassel core?', Prober::ERROR_PROTOCOL));
                return;
            }

            if (isset($buffer[3])) {
                $stream->removeListener('data', $fn);
                $deferred->resolve($binary->readUInt32($buffer));
            }
        };
        $stream->on('data', $fn);

        $stream->on('close', function() use ($deferred) {
            $deferred->reject(new \RuntimeException('Stream closed, does this (old?) server support probing?', Prober::ERROR_CLOSED));
        });

        return $deferred->promise();
    }
}
