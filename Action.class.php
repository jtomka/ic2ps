<?php

class Action
{
    const FOLD = 'fold';
    const CHECK = 'check';
    const CALL = 'call';
    const BET = 'bet';
    const RAISE = 'raise';

    const RETRN = 'return';
    const MUCK = 'muck';
    const RESULT = 'result';
    const NOSHOW = 'noshow';
    const SHOWDOWN = 'showdown';
    
    private $street;

    private $name;

    private $type;

    private $chips;

    private $to_chips;

    private $is_all_in;

    public function __construct($street, $name, $type, $chips = null, $to_chips = null, $is_all_in = false)
    {
        $this->setStreet($street);
        $this->setName($name);
        $this->setType($type);

        if (! is_null($chips))
            $this->setChips($chips);

        if (! is_null($to_chips))
            $this->setToChips($to_chips);

        $this->setIsAllIn($is_all_in);
    }

    private function setStreet($street)
    {
        Street::validate($street);

        $this->street = $street;

        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    private function setName($name)
    {
        Player::validateName($name);

        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public static function getShowdownTypes()
    {
        return array(Action::SHOWDOWN, Action::MUCK, Action::RESULT);
    }

    public static function getAllTypes()
    {
        return array_merge(array(self::FOLD, self::CHECK, self::CALL, self::BET, self::RAISE, self::RETRN), self::getShowdownTypes());
    }

    public static function validateType($type)
    {
	if (! in_array($type, self::getAllTypes()))
            throw new InvalidArgumentException(sprintf("Invalid action type `%s'", $type));
    }

    private function setType($type)
    {
        self::validateType($type);

        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    private function setChips(float $chips)
    {
        Hand::validateChipsAmount($chips);

        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    private function setToChips(float $to_chips)
    {
        Hand::validateChipsAmount($to_chips);

        $this->to_chips = $to_chips;

        return $this;
    }

    public function getToChips()
    {
        return $this->to_chips;
    }

    private function setIsAllIn($is_all_in)
    {
        $this->is_all_in = (boolean) $is_all_in;

        return $this;
    }

    public function getIsAllIn()
    {
        return (bool) $this->is_all_in;
    }
}

