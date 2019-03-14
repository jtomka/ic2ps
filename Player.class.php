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
            throw InvalidArgumentException(sprintf("Invalid seat number `%i'", $seat));

        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
    }

    public function setCards($card1, $card2)
    {
        Hand::validateCard($card1);
        Hand::validateCard($card2);

        $this->cards = array($card1, $card2);

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

    public function setIsHero($is_hero)
    {
        $this->is_hero = (bool) $is_hero;

        return $this;
    }
}

