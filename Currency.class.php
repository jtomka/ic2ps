<?php

class Currency extends Base
{
    const USD = 'usd';
    const EUR = 'eur';

    private $currency;

    private static $currency_data = array(
        self::USD => array(
            'symbol' => '$',
            'code' => 'USD',
            'instance' => null,
        ),
        self::EUR => array(
            'symbol' => '€',
            'code' => 'EUR',
            'instance' => null,
        ),
    );

    private function __construct($currency)
    {
        $this->currency = $currency;
    }

    public static function validate($currency)
    {
        if (! in_array($currency, array(self::USD, self::EUR)))
            throw new InvalidArgumentException(sprintf("Unsupported currency `%s'", $currency));
    }

    public static function getInstance($currency)
    {
        self::validate($currency);

        if (is_null(self::$currency_data[$currency]['instance']))
            self::$currency_data[$currency]['instance'] = new Currency($currency);

        return self::$currency_data[$currency]['instance'];
    }

    public function getSymbol()
    {
        return self::$currency_data[$this->currency]['symbol'];
    }

    public function getCode()
    {
        return self::$currency_data[$this->currency]['code'];
    }
}

