#!/usr/bin/env php

<?php

use Coolert\PackageTemplate\Application;

if (file_exists(dirname(dirname(__FILE__)).'/vendor/autoload.php')) {
    require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
} else if (file_exists(dirname(__FILE__).'/../../../autoload.php')) {
    require_once dirname(__FILE__).'/../../../autoload.php';
} else {
    throw new Exception('Can not load composer autoloader; Try running "composer install".');
}

$builder = new Application('Package Template', '@package_version@');
$builder->run();