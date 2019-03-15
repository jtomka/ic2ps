<?php

class Summary extends Base
{
    const FOLD = 'fold';
    const MUCK = 'muck';
    const WIN = 'win';
    const LOSS = 'loss';
    
    protected $seat;

    protected $name;

    protected $type;

    protected $street;

    protected $chips;

    protected $cards;

    public function __construct($seat, $name, $type)
    {
        $this->setSeat($seat);
        $this->setName($name);
        $this->setType($type);
    }

    private function setSeat($seat)
    {
        Player::validateSeat($seat);

        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
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

    public static function getAllTypes()
    {
        return array(self::FOLD, self::MUCK, self::WIN, self::LOSS);
    }

    public static function validateType($type)
    {
        if (! in_array($type, self::getAllTypes()))
            throw new InvalidArgumentException(sprintf("Invalid summary type `%s'", $type));
    }

    private function setType($type)
    {
        $this->validateType($type);

        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTypesWithStreet()
    {
        return array(self::FOLD);
    }

    public function setStreet($street)
    {
        if (! in_array($street, $this->getTypesWithStreet()))
            return new LogicException("Street not allowed for summary type");

        $this->street = $street;

        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function getTypesWithChips()
    {
        return array(self::WIN);
    }

    public function setChips($chips)
    {
        Chips::validate($chips);

        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    public function setCards($cards)
    {
        if (is_string($cards))
            $cards = explode(' ', $cards);

        foreach ($cards as $c)
            Hand::validateCard($c);

        $this->cards = $cards;

        return $this;
    }

    public function getCards()
    {
        return $this->cards;
    }
}

