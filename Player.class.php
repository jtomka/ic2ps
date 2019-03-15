<?php

class Player extends Base
{
    private $name;

    private $seat;

    private $cards;

    private $is_hero;

    private $position;

    public function __construct($seat, $name, $chips, $is_hero = false)
    {
        $this->setSeat($seat);
        $this->setName($name);
        $this->setChips($chips);
        $this->setIsHero($is_hero);
    }

    public static function validateSeat($seat)
    {
        if (! is_int($seat) || $seat < 1)
            throw new InvalidArgumentException(sprintf("Invalid seat number `%s'", $seat));
    }

    private function setSeat($seat)
    {
        $this->validateSeat($seat);

        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
    }

    public static function validateName($name)
    {
        if (empty($name))
            throw new InvalidArgumentException("Empty player name");
    }

    private function setName($name)
    {
        $this->validateName($name);

        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    private function setChips($chips)
    {
        Chips::validate($chips);

        $this->chips = $chips;

        return $this;
    }

    public function setCards($cards)
    {
        if (isset($this->cards))
            throw new LogicException("Change of player cards not allowed");

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

    public function getIsHero()
    {
        return $this->is_hero;
    }

    private function setIsHero($is_hero = true)
    {
        $this->is_hero = (boolean) $is_hero;

        return $this;
    }

    public function setPosition($position)
    {
        Position::validate($position);

        $this->position = $position;
    }

    public function getPosition()
    {
        return $this->position;
    }
}

