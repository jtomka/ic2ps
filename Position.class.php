<?php

class Position extends Base
{
    const BTN = 'btn';
    const SB = 'sb';
    const BB = 'bb';
    const UTG = 'utg';
    const UTG1 = 'utg1';
    const UTG2 = 'utg2';
    const UTG3 = 'utg3';
    const LJ = 'lj';
    const HJ = 'hj';
    const CO = 'co';

    private static $matrix = array(
        2 => array(self::SB,  self::BB),
        3 => array(self::BTN, self::SB, self::BB),
        4 => array(self::BTN, self::SB, self::BB, self::CO),
        5 => array(self::BTN, self::SB, self::BB, self::HJ, self::CO),
        6 => array(self::BTN, self::SB, self::BB, self::UTG, self::HJ, self::CO),
        7 => array(self::BTN, self::SB, self::BB, self::UTG, self::LJ, self::HJ, self::CO),
        8 => array(self::BTN, self::SB, self::BB, self::UTG, self::UTG1, self::LJ, self::HJ,
                   self::CO),
        9 => array(self::BTN, self::SB, self::BB, self::UTG, self::UTG1, self::UTG2, self::LJ,
                   self::HJ, self::CO),
        10 => array(self::BTN, self::SB, self::BB, self::UTG, self::UTG1, self::UTG2, self::UTG3,
                    self::LJ, self::HJ, self::CO)
    );

    public static function getAll($player_count = 10)
    {
        if (! is_int($player_count) || $player_count < 2 || $player_count > 10)
            throw new InvalidArgumentException("Invalid player count value");

        return self::$matrix[$player_count];
    }

    public static function validate($position, $player_count = 10)
    {
        if (! in_array($position, self::getAll($player_count)))
            throw new InvalidArgumentException("Invalid position");
    }

    public static function calculate($player_count, $after_button)
    {
        if (! is_int($after_button))
            throw new InvalidArgumentException("Invalid after button value");

        $after_button = $after_button % $player_count;
        if ($after_button < 0)
            $after_button = $player_count + $after_button;

        return self::getAll($player_count)[$after_button];
    }
}

