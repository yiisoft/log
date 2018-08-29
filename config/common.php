<?php

return [
    'app' => [
        'bootstrap' => [
            'logger' => 'logger',
        ],
    ],
    'logger' => [
        '__class' => yii\log\Logger::class,
        '__construct()' => [
            'targets' => [],
        ],
        'traceLevel' => $params['logger.traceLevel'],
    ],
];
