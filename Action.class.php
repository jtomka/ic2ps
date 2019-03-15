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

    public function __construct($street, $name, $type)
    {
        $this->setStreet($street);
        $this->setName($name);
        $this->setType($type);
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

    public static function getTypesWithoutChips()
    {
        return array(self::CHECK, self::FOLD);
    }

    public static function getTypesWithChips()
    {
        return array(self::CALL, self::BET, self::RAISE, self::RESULT, self::RETRN);
    }

    public function setChips($chips)
    {
        if (! in_array($this->getType(), $this->getTypesWithChips()))
            throw new LogicException("Chips value not allowed for type");

        Chips::validate($chips);

        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    public static function getTypesWithToChips()
    {
        return array(self::RAISE);
    }

    public function setToChips($to_chips)
    {
        if (! in_array($this->getType(), $this->getTypesWithToChips()))
            throw new LogicException("To chips value not allowed for type");

        Chips::validate($to_chips);

        $this->to_chips = $to_chips;

        return $this;
    }

    public function getToChips()
    {
        return $this->to_chips;
    }

    public static function getTypesWithAllIn()
    {
        return array(self::CALL, self::BET, self::RAISE);
    }

    public function setIsAllIn($is_all_in)
    {
        if (! in_array($this->getType(), $this->getTypesWithAllIn()))
            throw new LogicException("All-in not allowed for type");

        $this->is_all_in = (boolean) $is_all_in;

        return $this;
    }

    public function getIsAllIn()
    {
        return (bool) $this->is_all_in;
    }
}

