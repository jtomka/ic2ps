<?php

class Currency extends Base
{
    const USD = 'usd';
    const EUR = 'eur';

    public static function validate($currency)
    {
        if (! in_array($currency, array(self::USD, self::EUR)))
            throw new InvalidArgumentException(sprintf("Unsupported currency `%s'", $currency));
    }
}

