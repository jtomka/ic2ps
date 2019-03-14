<?php

interface ParserInterface
{
    public function getLine();

    public function getLineNo();

    // Return Hand object representation of next hand in file
    public function parseNextHand();
}

