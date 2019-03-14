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
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setSeat($seat)
    {
        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
    }

    public function setCards($card1, $card2)
    {
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

