<?php

class Position extends Base
{
    const UTG = 'utg';
    const UTG1 = 'utg1';
    const UTG2 = 'utg2';
    const LJ = 'lj';
    const HJ = 'hj';
    const EP = 'ep';
    const MP = 'mp';
    const CO = 'co';
    const BTN = 'btn';
    const SB = 'sb';
    const BB = 'bb';

    private static $matrix = array(
        TableSize::TWO => array(
            0 => self::SB,
            1 => self::BB,
        ),
        TableSize::SIX => array(
            0 => self::BTN,
            1 => self::SB,
            2 => self::BB,
            3 => self::UTG,
            4 => self::HJ,
            5 => self::CO,
        ),
        TableSize::NINE => array(
            0 => self::BTN,
            1 => self::SB,
            2 => self::BB,
            3 => self::UTG,
            4 => self::UTG1,
            5 => self::UTG2,
            6 => self::LJ,
            7 => self::HJ,
            8 => self::CO,
        ),
    );

    public static function getAll()
    {
	return array(self::UTG, self::UTG1, self::UTG2, self::LJ, self::HJ,
	    self::EP, self::MP, self::CO, self::BTN, self::SB, self::BB);
    }

    public static function validate($position)
    {
        if (! in_array($position, self::getAll()))
            throw new InvalidArgumentException("Invalid position");
    }

    public static function calculate($table_size, $after_button)
    {
        TableSize::validate($table_size);

        if (! is_int($after_button))
            throw new InvalidArgumentException("Invalid after button value");

        $after_button = $after_button % $table_size;
        if ($after_button < 0)
            $after_button = $table_size + $after_button;

        return self::$matrix[$table_size][$after_button];
    }
}

