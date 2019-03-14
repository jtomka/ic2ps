<?php

ini_set('date.timezone', 'UTC');

spl_autoload_register(function ($class_name) {
    include $class_name . '.class.php';
});

