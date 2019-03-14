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
    const SHOWDOWN = 'showdown';
    
    protected $street;

    protected $player;

    protected $action;

    protected $chips;

    protected $to_chips;

    protected $is_all_in;

    public function setStreet(string $street)
    {
        $this->street = $street;

        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function setPlayer(string $player)
    {
        $this->player = $player;

        return $this;
    }

    public function getPlayer()
    {
        return $this->player;
    }

    public function setAction(string $action)
    {
        $this->action = $action;

        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setChips(float $chips)
    {
        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    public function setToChips(float $to_chips)
    {
        $this->to_chips = $to_chips;

        return $this;
    }

    public function getToChips()
    {
        return $this->to_chips;
    }

    public function setIsAllIn(bool $is_all_in)
    {
        $this->is_all_in = $is_all_in;

        return $this;
    }

    public function getIsAllIn()
    {
        return (bool) $this->is_all_in;
    }
}

