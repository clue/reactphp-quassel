<?php

namespace Clue\React\Quassel\Io;

/** @internal */
class Binary
{
    public function writeUInt32($num)
    {
        return pack('N', $num);
    }

    public function readUInt32($data)
    {
        $d = unpack('Nint', $data);

        return $d['int'];
    }
}
