<?php

class Pot extends Base
{
    const MAIN = 0;

    private $number;

    private $chips;

    public function __construct($number, $chips)
    {
        $this->setNumber($number);
        $this->setChips($chips);
    }

    public static function validateNumber($number)
    {
        if (! is_int($number) || $number < 0)
            throw new InvalidArgumentException(sprintf("Invalid pot number `%s'", $number));
    }

    private function setNumber($number)
    {
        $this->validateNumber($number);

        $this->number = $number;
    }

    private function setChips($chips)
    {
        Chips::validate($chips);

        $this->chips = $chips;
    }
}

