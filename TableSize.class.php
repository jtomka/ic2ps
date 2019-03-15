<?php

class TableSize extends Base
{
    const TWO = 2;
    const SIX = 6;
    const NINE = 9;

    public static function validate($table_size)
    {
        if (! in_array($table_size, array(self::TWO, self::SIX, self::NINE)))
            throw new InvalidArgumentException(sprintf("Unsupported table size `%s'", $table_size));
    }
}

