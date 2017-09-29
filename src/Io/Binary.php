<?php

namespace Clue\React\Quassel\Io;

/** @internal */
class Binary
{
    public static function writeUInt32($num)
    {
        return pack('N', $num);
    }

    public static function readUInt32($data)
    {
        $d = unpack('Nint', $data);

        return $d['int'];
    }
}
