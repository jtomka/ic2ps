<?php

class Limit extends Base
{
    const NOLIMIT = 'nolimit';

    public static function validate($limit)
    {
        if (! in_array($limit, array(self::NOLIMIT)))
            throw new InvalidArgumentException(sprintf("Unsupported limit type `%s'", $limit));
    }

}

