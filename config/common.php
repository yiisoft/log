<?php

/** @var array $params */

return [
    'app' => [
        'bootstrap' => [
            'logger' => 'logger',
        ],
    ],
    'logger' => [
        '__class' => Yiisoft\Log\Logger::class,
        '__construct()' => [
            'targets' => [],
        ],
        'traceLevel' => $params['logger.traceLevel'],
    ],
];
