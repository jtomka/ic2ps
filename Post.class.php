<?php

class Post extends GamePost
{
    const STRADDLE = 'straddle';
    const DEAD = 'dead';

    private $player;

    public static function getAllTypes()
    {
        return array_merge(parent::getAllTypes(), array(self::DEAD, self::STRADDLE));
    }

    public static function validate($type)
    {
        if (! in_array($type, self::getAllTypes()))
            throw new InvalidArgumentException(sprintf("Unsupported post type `%s'", $type));
    }

    public static function isSingle($type)
    {
        self::validate($type);

        return in_array($type, array(self::SB, self::BB));
    }

    public function __construct($type, $chips, $player)
    {
        parent::__construct($type, $chips);
        $this->setPlayer($player);
    }

    public function setPlayer($player)
    {
        Player::validateName($player);

        $this->player = $player;

        return $this;
    }

    public function getPlayer()
    {
        return $this->player;
    }
}

