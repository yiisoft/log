<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Yiisoft\CodeStyle\Rector\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php80: true)
    ->withSets([
        SetList::YII_CORE,
    ])
    ->withSkip([
        ClosureToArrowFunctionRector::class,
    ]);
