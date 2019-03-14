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
    
    private $street;

    private $player;

    private $action;

    private $chips;

    private $to_chips;

    private $is_all_in;

    public function setStreet(string $street)
    {
        if (! in_array($street, array(Hand::STREET_PREFLOP, Hand::STREET_FLOP,
                Hand::STREET_TURN, HAND::STREET_RIVER)))
            throw new InvalidArgumentException(sprintf('Invalid street value `%s\'', $street));

        $this->street = $street;

        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function setPlayer(string $player)
    {
        if (empty($player))
            throw new InvalidArgumentException("Empty player name");

        $this->player = $player;

        return $this;
    }

    public function getPlayer()
    {
        return $this->player;
    }

    public function setAction(string $action)
    {
	if (! in_array($action, array(self::FOLD, self::CHECK, self::CALL, self::BET, self::RAISE, self::RETRN, self::MUCK, self::RESULT, self::SHOWDOWN)))
	    throw new InvalidArgumentException(sprintf("Invalid action value `%s'", $action));

        $this->action = $action;

        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setChips(float $chips)
    {
        Hand::validateChipsAmount($chips);

        $this->chips = $chips;

        return $this;
    }

    public function getChips()
    {
        return $this->chips;
    }

    public function setToChips(float $to_chips)
    {
        Hand::validateChipsAmount($to_chips);

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

