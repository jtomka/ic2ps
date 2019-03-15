<?php

class Format extends Base
{
    const CASH_GAME = 'cash';
    const TOURNAMENT = 'tournament';

    public static function getAll()
    {
        return array(self::CASH_GAME, self::TOURNAMENT);
    }

    public static function validate($format)
    {
        if (! in_array($format, self::getAll()))
            throw new InvalidArgumentException(sprintf("Unsupported format `%s'", $format));
    }
}

