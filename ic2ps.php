<?php
const IC2PS_DEBUG = true;

const IC_DEFAULT_HH_DIR = 'Ignition Casino Poker/Hand History';
const PS_DEFAULT_HH_DIR = 'Library/Application Support/PokerStars/HandHistory';

ini_set('date.timezone', 'UTC');

spl_autoload_register(function ($class_name) {
    include $class_name . '.class.php';
});

include "functions.php";

$ic_hh_dir = getenv("HOME") . '/' . IC_DEFAULT_HH_DIR;
//$ps_hh_dir = getenv("HOME") . '/' . PS_DEFAULT_HH_DIR;
// while testing
$ps_hh_dir = getcwd() . '/' . PS_DEFAULT_HH_DIR;

$ic2ps = new Ic2Ps($ic_hh_dir, $ps_hh_dir);
try {
    $ic2ps->processHh();
} catch (Exception $e) {
    fatal($e->getMessage());
}

