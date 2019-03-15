<?php

class Game extends Base
{
    const HOLDEM = 'holdem';

    public static function getAll()
    {
        return array(self::HOLDEM);
    }

    public static function validate($game)
    {
        if (! in_array($game, self::getAll()))
            throw new InvalidArgumentException(sprintf("Unsupported game `%s'", $game));
    }
}
