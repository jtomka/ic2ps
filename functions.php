<?php

function err($format, ...$args) {
    fputs(STDERR, vsprintf($format, $args));
}

function error($format, ...$args) {
    call_user_func_array('err', func_get_args());
    err("\n");
}

function deb($format, ...$args) {
    if (IGNITION2PS_DEBUG)
        return call_user_func_array('err', func_get_args());
}

function debug($format, ...$args) {
    if (IGNITION2PS_DEBUG)
        return call_user_func_array('error', func_get_args());
}

function fatal($format, ...$args) {
    call_user_func_array('error', func_get_args());
    exit(1);
}

