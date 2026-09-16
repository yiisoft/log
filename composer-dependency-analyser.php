<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // config/events-web.php and config/events-console.php reference event classes from
    // yiisoft/yii-http and symfony/console purely as optional integration hooks (array keys);
    // neither package is a real dependency of this logger.
    ->ignoreUnknownClasses(['Yiisoft\Yii\Http\Event\AfterEmit'])
    ->ignoreErrorsOnPackage('symfony/console', [ErrorType::SHADOW_DEPENDENCY]);
