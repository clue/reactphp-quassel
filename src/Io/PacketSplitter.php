<?php

namespace Clue\React\Quassel\Io;

use Clue\React\Quassel\Io\Binary;

class PacketSplitter
{
    private $buffer = '';
    private $binary;

    public function __construct(Binary $binary)
    {
        $this->binary = $binary;
    }

    public function push($chunk, $fn)
    {
        $this->buffer .= $chunk;

        while (isset($this->buffer[3])) {
            // buffer contains at least packet length
            $length = $this->binary->readUInt32(substr($this->buffer, 0, 4));

            // buffer contains last byte of packet
            if (!isset($this->buffer[3 + $length])) {
                return;
            }

            // parse packet and advance buffer
            call_user_func($fn, substr($this->buffer, 4, $length));
            $this->buffer = substr($this->buffer, 4 + $length);
        }
    }

    /**
     * encode the given packet data to include framing (packet length)
     *
     * @param string $packet binary packet contents
     * @return string binary packet contents prefixed with frame length
     */
    public function writePacket($packet)
    {
        // TODO: legacy compression / decompression
        // legacy protocol writes variant via DataStream to ByteArray
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L105
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L63
        //$data = $this->types->writeByteArray($data);

        // raw data is prefixed with length, then written
        // https://github.com/quassel/quassel/blob/master/src/common/remotepeer.cpp#L241
        return $this->binary->writeUInt32(strlen($packet)) . $packet;
    }
}
