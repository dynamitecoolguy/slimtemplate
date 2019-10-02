<?php

return [
    'userdb' => [
        'host' => 'mysql',
        'dbname' => 'userdb',
        'user' => 'scott',
        'password' => 'tiger'
    ],
    'logdb' => [
        'host' => 'postgresql',
        'dbname' => 'logdb',
        'user' => 'root',
        'password' => 'hogehoge'
    ],
    'redis' => [
        'host' => 'redis'
    ],
    'dynamodb' => [
        'endpoint' => 'http://dynamodb:8000',
        'region' => 'ap-northeast-1'
    ],
    'storage' => [
        'endpoint' => 'http://storage:9000',
        'region' => 'ap-northeast-1'
    ]
];