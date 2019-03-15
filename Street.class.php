<?php

class Street extends Base
{
    const PREFLOP = 'preflop';
    const FLOP = 'flop';
    const TURN = 'turn';
    const RIVER = 'river';

    public static function getAllPostflop()
    {
        return array(self::FLOP, self::TURN, self::RIVER);
    }

    public static function validatePostflop($street)
    {
        if (! in_array($street, self::getAllPostflop()))
            throw new InvalidArgumentException(sprintf("Invalid postflop street `%s'", $street));
    }

    public static function getAll()
    {
        return array_merge(array(self::PREFLOP), self::getAllPostflop());
    }

    public static function validate($street)
    {
        if (! in_array($street, self::getAll()))
            throw new InvalidArgumentException(sprintf("Invalid street `%s'", $street));
    }

    public static function validateCardCount($street, $card_count)
    {
        if (($street == self::FLOP && $card_count != 3)
         || ($street == self::TURN && $card_count != 1)
         || ($street == self::RIVER && $card_count != 1)) {
            throw new InvalidArgumentException(sprintf("Invalid nuber of community cards (%d) for street `%s'", $card_count, $street));
        }
    }
}
