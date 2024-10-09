<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\FileCache\FileCache;

Dump::init();


$obj = new FileCache(__DIR__ . '/../cache');

// $obj->saveToClaster('dawd', 'claster', 123);

$res = $obj->deleteAllByNameFromClaster('claster');

de($res);
