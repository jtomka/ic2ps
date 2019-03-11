<?php
const IGNITION2PS_DEBUG = true;

const IGNITION_DEFAULT_HH_DIR = 'Ignition Casino Poker/Hand History';

ini_set('date.timezone', 'UTC');

spl_autoload_register(function ($class_name) {
    include $class_name . '.class.php';
});

include "functions.php";

$hh_dir = getenv("HOME") . '/' . IGNITION_DEFAULT_HH_DIR;

$ignition2ps = new Ignition2Ps($hh_dir);
try {
    $ignition2ps->processIgnitionHh();
} catch (Exception $e) {
    fatal($e->getMessage());
}

