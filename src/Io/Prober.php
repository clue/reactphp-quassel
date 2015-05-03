<?php

namespace Clue\React\Quassel\Io;

use React\Stream\Stream;
use React\Promise\Deferred;

class Prober
{
    private $binary;

    public function __construct(Binary $binary = null)
    {
        if ($binary === null) {
            $binary = new Binary();
        }
        $this->binary = $binary;
    }

    public function probe(Stream $stream, $compression = false, $encryption = false)
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

        // list of supported protocol types
        // last item has to be handled separately
        //$types = array(Protocol::TYPE_DATASTREAM);
        $types = array(Protocol::TYPE_LEGACY);
        $last = array_pop($types);

        foreach ($types as $type) {
            $stream->write($binary->writeUInt32($num));
        }
        $stream->write($binary->writeUInt32($last | Protocol::TYPELIST_END));

        $deferred = new Deferred(function ($resolve, $reject) use ($stream) {
            $reject(new \RuntimeException('Cancelled'));
        });

        $buffer = '';
        $fn = function ($data) use (&$buffer, &$fn, $stream, $deferred, $binary) {
            $buffer .= $data;

            if (isset($buffer[4])) {
                $stream->removeListener('data', $fn);
                $deferred->reject(new \UnexpectedValueException('Expected 4 bytes response, received more data, is this a quassel core?'));
                return;
            }

            if (isset($buffer[3])) {
                $stream->removeListener('data', $fn);
                $deferred->resolve($binary->readUInt32($buffer));
            }
        };
        $stream->on('data', $fn);

        $stream->on('close', function() use ($deferred) {
            $deferred->reject(new \RuntimeException('Stream closed, does this (old?) server support probing?'));
        });

        return $deferred->promise();
    }
}
