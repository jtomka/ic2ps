<?php

class Post extends GamePost
{
    const STRADDLE = 'straddle';
    const DEAD = 'dead';

    private $name;

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

    public function __construct($type, $chips, $name)
    {
        parent::__construct($type, $chips);
        $this->setName($name);
    }

    public function setName($name)
    {
        Player::validateName($name);

        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }
}

