<?php

require __DIR__.'/../vendor/autoload.php';

Symfony\Component\Debug\ErrorHandler::register();

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);

if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

require '../application.php';
require '../config.php';

$app->run();
