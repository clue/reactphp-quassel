<?php

namespace Clue\React\Quassel\Io;

use Clue\React\Quassel\Io\Binary;

/** @internal */
class PacketSplitter
{
    private $buffer = '';

    /**
     * maximum allowed size for a single incoming packet (16 MB)
     *
     * This help to avoid allocating excessive string buffers, as most packets
     * are rather small (a few kilobytes). The biggest known package is a legacy
     * SessionInit with ~1.4 MB for common networks, while the newer datastream
     * protocol uses only ~0.6 MB for the same message.
     *
     * @var int
     */
    const MAX_SIZE = 16000000;

    public function push($chunk, $fn)
    {
        $this->buffer .= $chunk;

        while (isset($this->buffer[3])) {
            // buffer contains at least packet length
            $length = Binary::readUInt32(substr($this->buffer, 0, 4));
            if ($length > self::MAX_SIZE) {
                throw new \OverflowException('Packet size of ' . $length . ' bytes exceeds maximum of ' . self::MAX_SIZE . ' bytes');
            }

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
     * Checks whether there's any incomplete data in the incoming buffer
     *
     * @return bool
     */
    public function isEmpty()
    {
        return ($this->buffer === '');
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
        return Binary::writeUInt32(strlen($packet)) . $packet;
    }
}
