#!/usr/bin/env php
<?php
ini_set('date.timezone', 'UTC');

spl_autoload_register(function ($class_name) {
    include $class_name . '.class.php';
});

const IC_HH_DIR = 'Ignition Casino Poker/Hand History';
const PS_HH_DIR = 'Library/Application Support/PokerStars/HandHistory';

$reader = new IcHhReader(getenv('HOME') . '/' . IC_HH_DIR);
$writer = new PsHhWriter(getenv('HOME') . '/' . PS_HH_DIR);

Converter::run($reader, $writer);

