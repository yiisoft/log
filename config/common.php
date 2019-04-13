<?php

return [
    'app' => [
        'bootstrap' => [
            'logger' => 'logger',
        ],
    ],
    'logger' => [
        '__class' => Yii\Log\Logger::class,
        '__construct()' => [
            'targets' => [],
        ],
        'traceLevel' => $params['logger.traceLevel'],
    ],
];
