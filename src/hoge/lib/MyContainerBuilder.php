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
    /**
     * @var ApplicationSetting
     */
    private $setting;

    /**
     * MyContainerBuilder constructor.
     * @param string $containerClass
     */
    public function __construct(string $containerClass = Container::class)
    {
        parent::__construct($containerClass);

        $this->setting = ApplicationSetting::getInstance();

        $this->addDefaultDefinitions();
    }

    /**
     * デフォルトの定義
     */
    private function addDefaultDefinitions(): void
    {
        $this->addDefinitions([
            'userdb' => function (ContainerInterface $container)
            {
                $dsn = 'mysql:host=' . $this->setting->getSettingValue('userdb', 'host')
                    . ';dbname=' . $this->setting->getSettingValue('userdb', 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->setting->getSettingValue('userdb', 'user'),
                    $this->setting->getSettingValue('userdb', 'password'),
                    $options
                );
            },
            'logdb' => function (ContainerInterface $container)
            {
                $dsn = 'pgsql:host=' . $this->setting->getSettingValue('logdb', 'host')
                    . ';dbname=' . $this->setting->getSettingValue('logdb', 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->setting->getSettingValue('logdb', 'user'),
                    $this->setting->getSettingValue('logdb', 'password'),
                    $options
                );
            },
            'redis' => function (ContainerInterface $container)
            {
                $redis = new Redis();
                $redis->connect($this->setting->getSettingValue('redis', 'host'));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

                return $redis;
            },
            'dynamodb' => function (ContainerInterface $container)
            {
                $sdk = new Sdk([
                    'endpoint' => $this->setting->getSettingValue('dynamodb', 'endpoint'),
                    'region' => $this->setting->getSettingValue('dynamodb', 'region'),
                    'version' => '2012-08-10',
                    'credentials' => [
                        'key' => $this->setting->getSettingValue('dynamodb', 'key'),
                        'secret' => $this->setting->getSettingValue('dynamodb', 'secret')
                    ]
                ]);

                return $sdk->createDynamoDb();
            },
            'storage' => function (ContainerInterface $container)
            {
                $sdk = new Sdk([
                    'endpoint' => $this->setting->getSettingValue('storage', 'endpoint'),
                    'region' => $this->setting->getSettingValue('storage', 'region'),
                    'version' => '2006-03-01',
                    'credentials' => [
                        'key' => $this->setting->getSettingValue('storage', 'key'),
                        'secret' => $this->setting->getSettingValue('storage', 'secret')
                    ],
                    'use_path_style_endpoint' => true
                ]);
                return $sdk->createS3();
            }
        ]);
    }
}

