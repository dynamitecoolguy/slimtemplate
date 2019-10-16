<?php

namespace Hoge;

use Aws\Sdk;
use PDO;
use Psr\Container\ContainerInterface;

use DI\Container;
use DI\ContainerBuilder;
use Redis;

class MyContainerBuilder extends ContainerBuilder
{
    public function __construct(string $containerClass = Container::class)
    {
        parent::__construct($containerClass);

        $this->addDefaultDefinitions();
    }

    private function addDefaultDefinitions(): void
    {
        $parsed = yaml_parse_file(__DIR__ . '/../../settings.yml');

        $this->addDefinitions(['settings' => $parsed]);
        $this->addDefinitions([
            'userdb' => function (ContainerInterface $container)
            {
                $settings = $container->get('settings')['userdb'];
                $dsn = 'mysql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'];
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO($dsn, $settings['user'], $settings['password'], $options);
            },
            'logdb' => function (ContainerInterface $container)
            {
                $settings = $container->get('settings')['logdb'];
                $dsn = 'pgsql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'];
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO($dsn, $settings['user'], $settings['password'], $options);
            },
            'redis' => function (ContainerInterface $container)
            {
                $settings = $container->get('settings')['redis'];
                $redis = new Redis();
                $redis->connect($settings['host']);
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

                return $redis;
            },
            'dynamodb' => function (ContainerInterface $container)
            {
                $settings = $container->get('settings')['dynamodb'];

                $sdk = new Sdk([
                    'endpoint' => $settings['endpoint'],
                    'region' => $settings['region'],
                    'version' => '2012-08-10',
                    'credentials' => [
                        'key' => $settings['key'],
                        'secret' => $settings['secret']
                    ]
                ]);

                $dynamodb = $sdk->createDynamoDb();
                return $dynamodb;
            },
            'storage' => function (ContainerInterface $container)
            {
                $settings = $container->get('settings')['storage'];
                $sdk = new Sdk([
                    'endpoint' => $settings['endpoint'],
                    'region' => $settings['region'],
                    'version' => '2006-03-01',
                    'credentials' => [
                        'key' => $settings['key'],
                        'secret' => $settings['secret']
                    ],
                    'use_path_style_endpoint' => true
                ]);
                $s3 = $sdk->createS3();
                return $s3;
            }
        ]);
    }
}