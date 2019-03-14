<?php

class Summary extends Base
{
    const FOLD = 'fold';
    const MUCK = 'muck';
    const WIN = 'win';
    
    protected $seat;

    protected $player;

    protected $action;

    protected $street;

    protected $cards;

    protected $chips;

    protected $shows;

    public function setSeat($seat)
    {
        $this->seat = $seat;

        return $this;
    }

    public function getSeat()
    {
        return $this->seat;
    }

    public function setPlayer($player)
    {
        $this->player = $player;

        return $this;
    }

    public function getPlayer()
    {
        return $this->player;
    }

    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setStreet($street)
    {
        $this->street = $street;

        return $this;
    }

    public function getStreet()
    {
        return $this->street;
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

    public function setChips($chips)
    {
        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    public function setShows($shows)
    {
        $this->shows = $shows;

        return $this;
    }

    public function getShows()
    {
        return $this->shows;
    }

}

