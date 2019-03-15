<?php

class Chips extends Base
{
    public static function validate($chips)
    {
        if (! is_numeric($chips) || $chips < 0)
            throw new InvalidArgumentException(sprintf("Invalid chips amount, `%f'", $chips));
    }
}

