<?php

class Player extends Base
{
    protected $name;

    protected $seat;

    protected $cards;

    protected $is_hero;

    public function __construct()
    {
        $this->cards = array();
    }

    public function setName($name)
    {
        Hand::validatePlayerName($name);

        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setSeat($seat)
    {
        if (! is_int($seat) || $seat < 1)
            throw new InvalidArgumentException(sprintf("Invalid seat number `%i'", $seat));

        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
    }

    public function setCards($cards)
    {
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
        return (bool) $this->is_hero;
    }

    public function setIsHero($is_hero = true)
    {
        $this->is_hero = (bool) $is_hero;

        return $this;
    }
}

