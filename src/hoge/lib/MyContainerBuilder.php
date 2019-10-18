<?php

namespace Hoge;

use Aws\Exception\AwsException;
use Aws\Sdk;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\WorkSpaces\Exception\WorkSpacesException;
use PDO;
use phpDocumentor\Reflection\Types\Mixed_;
use Psr\Container\ContainerInterface;

use DI\Container;
use DI\ContainerBuilder;
use Redis;

class MyContainerBuilder extends ContainerBuilder
{
    /**
     * Secrets Manager上の設定格納キー
     */
    const SECRET_VALUE_KEY = 'slimtemplate_secret';

    /**
     * localにcacheするsecretのTTL
     */
    const SECRET_TTL = 60;

    /**
     * @var array
     * 取得済みの値
     */
    private $replacedText = [];

    /**
     * MyContainerBuilder constructor.
     * @param string $containerClass
     */
    public function __construct(string $containerClass = Container::class)
    {
        parent::__construct($containerClass);

        $this->addDefaultDefinitions();
    }

    /**
     * デフォルトの定義
     */
    private function addDefaultDefinitions(): void
    {
        // 設定用YAMLファイル読み込み
        // 設定ファイルはdockerによって/var/www下にコピーされる
        $parsed = yaml_parse_file(__DIR__ . '/../../settings.yml');

        $this->addDefinitions([
            'settings' => $parsed,
            'userdb' => function (ContainerInterface $container)
            {
                $setting = $this->getSettingArray($container, 'userdb');
                $dsn = 'mysql:host=' . $this->getSettingValue($setting, 'host')
                    . ';dbname=' . $this->getSettingValue($setting, 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->getSettingValue($setting, 'user'),
                    $this->getSettingValue($setting, 'password'),
                    $options
                );
            },
            'logdb' => function (ContainerInterface $container)
            {
                $setting = $this->getSettingArray($container, 'logdb');
                $dsn = 'pgsql:host=' . $this->getSettingValue($setting, 'host')
                    . ';dbname=' . $this->getSettingValue($setting, 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->getSettingValue($setting, 'user'),
                    $this->getSettingValue($setting, 'password'),
                    $options
                );
            },
            'redis' => function (ContainerInterface $container)
            {
                $setting = $this->getSettingArray($container, 'redis');
                $redis = new Redis();
                $redis->connect($this->getSettingValue($setting, 'host'));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

                return $redis;
            },
            'dynamodb' => function (ContainerInterface $container)
            {
                $setting = $this->getSettingArray($container, 'dynamodb');
                $sdk = new Sdk([
                    'endpoint' => $this->getSettingValue($setting, 'endpoint'),
                    'region' => $this->getSettingValue($setting, 'region'),
                    'version' => '2012-08-10',
                    'credentials' => [
                        'key' => $this->getSettingValue($setting, 'key'),
                        'secret' => $this->getSettingValue($setting, 'secret')
                    ]
                ]);

                return $sdk->createDynamoDb();
            },
            'storage' => function (ContainerInterface $container)
            {
                $setting = $this->getSettingArray($container, 'storage');
                $sdk = new Sdk([
                    'endpoint' => $this->getSettingValue($setting, 'endpoint'),
                    'region' => $this->getSettingValue($setting, 'region'),
                    'version' => '2006-03-01',
                    'credentials' => [
                        'key' => $this->getSettingValue($setting, 'key'),
                        'secret' => $this->getSettingValue($setting, 'secret')
                    ],
                    'use_path_style_endpoint' => true
                ]);
                return $sdk->createS3();
            }
        ]);
    }

    /**
     * settings.ymlファイルの中から指定されたカテゴリの設定を返す
     * @param ContainerInterface $container
     * @param string $category
     * @return array
     */
    private function getSettingArray(ContainerInterface $container, string $category): array
    {
        $settingArray = $container->get('settings');
        if (!isset($settingArray[$category])) {
            return []; // TODO: 本当はException
        }
        return $settingArray[$category];
    }

    /**
     * getSettingArrayで取得した配列から、指定されたキーの値を取得する
     * @param array $setting
     * @param string $key
     * @return mixed
     */
    private function getSettingValue(array $setting, string $key)
    {
        if (!isset($setting[$key])) {
            return false; // TODO: 本当はException
        }

        $value = $setting[$key];
        if (strpos('{{', $value) === false) {
            return $value; // 置換の必要が無ければその値のまま返す
        }

        if (preg_match('/^(.*){{(.*)}}(.*)$/', $value, $matches)) { // {{KEY_NAME}}か?
            return $value; // 置換の必要が無ければその値のまま返す
        }

        return $matches[0] . $this->replaceValue($matches[1]) . $matches[2];
    }

    /**
     * @param string $original
     * @return mixed
     */
    private function replaceValue(string $original)
    {
        // このリクエストで置換済みの値か?
        if (isset($this->replacedText[$original])) {
            return $this->replacedText[$original];
        }

        // apcuに保存されている値か?
        $fetchedValue = apcu_fetch($original);
        if ($fetchedValue) {
            $this->replacedText[$original] = $fetchedValue;
            return $fetchedValue;
        }

        // AWS Secret Managerから取得 (json形式)
        $client = new SecretsManagerClient([
            'profile' => 'default',
            'version' => '2017-10-17',
            'region' => 'ap-northeast-1'
        ]);

        try {
            $result = $client->getSecretValue([
                'SecretId' => static::SECRET_VALUE_KEY
            ]);
        } catch (AwsException $e) {
            return false;  // TODO: 本当はException
        }
        if (isset($result['SecretString'])) {
            $secret = $result['SecretString'];
        } else {
            $secret = base64_decode($result['SecretBinary']);
        }

        // jsonデータを展開し、apcuとローカルに格納する
        foreach (json_decode($secret) as $key => $value) {
            if (!apcu_exists($key)) {
                apcu_store($key, $value, static::SECRET_TTL);
            }
            $this->replacedText[$key] = $value;
        }

        if (!isset($this->replacedText[$original])) {
            return false; // TODO: 本当はException
        }

        return $this->replacedText[$original];
    }
}