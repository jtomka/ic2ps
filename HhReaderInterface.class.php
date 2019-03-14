<?php

interface HhReaderInterface extends HhInterface
{
    public function getAccounts();

    public function parseAccountHandHistory($account);
}

