<?php 

if (file_exists($file = __DIR__.'/../vendor/.composer/autoload.php')) {
    $loader = require_once $file;
} else {
    throw new RuntimeException('Install dependencies to run test suite.');
}

$loader->add('Jmikola\\WildcardEventDispatcher', __DIR__.'/../src');
