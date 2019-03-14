#!/usr/bin/env php
<?php
include('bootstrap.php');

const IC_HH_DIR = 'Ignition Casino Poker/Hand History';
const PS_HH_DIR = 'Library/Application Support/PokerStars/HandHistory';

$reader = new IcHhReader(getenv('HOME') . '/' . IC_HH_DIR);
$writer = new PsHhWriter(getenv('HOME') . '/' . PS_HH_DIR);

Converter::run($reader, $writer);

