<?php

class GamePost extends Base
{
    const SB = 'sb';
    const BB = 'bb';
    const ANTE = 'ante';

    private $type;
    private $chips;

    public static function getAllTypes()
    {
        return array(self::SB, self::BB, self::ANTE);
    }

    public static function validate($type)
    {
        if (! in_array($type, self::getAllTypes()))
            throw new InvalidArgumentException(sprintf("Unsupported game post type `%s'", $type));
    }

    public function __construct($type, $chips)
    {
        $this->setType($type);
        $this->setChips($chips);
    }

    public function setType($type)
    {
        $this->validate($type);

        $this->post = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setChips($chips)
    {
        Chips::validate($chips);

        $this->chips = $chips;

        return $this;
    }

    public function getChips($chips)
    {
        return $this->chips;
    }
}

