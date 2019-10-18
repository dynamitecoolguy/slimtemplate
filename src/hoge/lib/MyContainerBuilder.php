<?php

namespace Hoge;

use Aws\Credentials\CredentialProvider;
use Aws\Exception\AwsException;
use Aws\Sdk;
use PDO;
use Psr\Container\ContainerInterface;

use DI\Container;
use DI\ContainerBuilder;
use Redis;

class MyContainerBuilder extends ContainerBuilder
{
    /**
     * 設定ファイルのパス
     */
    const SETTINGS_YAML = '/usr/local/etc/myapp/settings.yml';

    /**
     * 設定ファイルのパス
     */
    const SSM_CREDENTIALS_PROFILE = 'default';
    const SSM_CREDENTIALS_PATH = '/usr/local/etc/myapp/credentials/ssm';

    /**
     * localにcacheするparameterのTTL
     */
    const PARAMETER_TTL = 60;

    /**
     * @var array
     * 取得済みの値
     */
    private $replacedText = [];

    /**
     * @var array
     * settings.yamlの内容
     */
    private $settings = [];

    /**
     * MyContainerBuilder constructor.
     * @param string $containerClass
     */
    public function __construct(string $containerClass = Container::class)
    {
        parent::__construct($containerClass);

        $this->settings = yaml_parse_file(static::SETTINGS_YAML);

        $this->addDefaultDefinitions();
    }

    /**
     * デフォルトの定義
     */
    private function addDefaultDefinitions(): void
    {
        $this->addDefinitions([
            'settings' => $this->settings,
            'userdb' => function (ContainerInterface $container)
            {
                $dsn = 'mysql:host=' . $this->getSettingValue('userdb', 'host')
                    . ';dbname=' . $this->getSettingValue('userdb', 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->getSettingValue('userdb', 'user'),
                    $this->getSettingValue('userdb', 'password'),
                    $options
                );
            },
            'logdb' => function (ContainerInterface $container)
            {
                $dsn = 'pgsql:host=' . $this->getSettingValue('logdb', 'host')
                    . ';dbname=' . $this->getSettingValue('logdb', 'dbname');
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                return new PDO(
                    $dsn,
                    $this->getSettingValue('logdb', 'user'),
                    $this->getSettingValue('logdb', 'password'),
                    $options
                );
            },
            'redis' => function (ContainerInterface $container)
            {
                $redis = new Redis();
                $redis->connect($this->getSettingValue('redis', 'host'));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

                return $redis;
            },
            'dynamodb' => function (ContainerInterface $container)
            {
                $sdk = new Sdk([
                    'endpoint' => $this->getSettingValue('dynamodb', 'endpoint'),
                    'region' => $this->getSettingValue('dynamodb', 'region'),
                    'version' => '2012-08-10',
                    'credentials' => [
                        'key' => $this->getSettingValue('dynamodb', 'key'),
                        'secret' => $this->getSettingValue('dynamodb', 'secret')
                    ]
                ]);

                return $sdk->createDynamoDb();
            },
            'storage' => function (ContainerInterface $container)
            {
                $sdk = new Sdk([
                    'endpoint' => $this->getSettingValue('storage', 'endpoint'),
                    'region' => $this->getSettingValue('storage', 'region'),
                    'version' => '2006-03-01',
                    'credentials' => [
                        'key' => $this->getSettingValue('storage', 'key'),
                        'secret' => $this->getSettingValue('storage', 'secret')
                    ],
                    'use_path_style_endpoint' => true
                ]);
                return $sdk->createS3();
            }
        ]);
    }

    /**
     * 指定されたカテゴリ内のキーの値を取得する
     * @param string $category
     * @param string $key
     * @return mixed
     */
    private function getSettingValue(string $category, string $key)
    {
        if (!isset($this->settings[$category]) || !isset($this->settings[$category][$key])) {
            return false; // TODO: 本当はException
        }
        $value = $this->settings[$category][$key];

        if (strpos($value, '$$') === false) {
            return $value; // 置換の必要が無ければその値のまま返す
        }

        if (!preg_match('/^(.*)\$\$(.*)\$\$(.*)$/', $value, $matches)) { // {{KEY_NAME}}か?
            return $value; // 置換の必要が無ければその値のまま返す
        }

        return $matches[1] . $this->replaceValue($matches[2]) . $matches[3];
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

        // SSMサービスへの認証
        $provider = CredentialProvider::ini(
            static::SSM_CREDENTIALS_PROFILE,
            static::SSM_CREDENTIALS_PATH
        );
        $memoizedProvider = CredentialProvider::memoize($provider);

        // SSM Clientの取得
        $ssmSetting = $this->settings['ssm'];
        $sdk = new Sdk([
            'endpoint' => $ssmSetting['endpoint'],
            'region' => $ssmSetting['region'],
            'version' => '2014-11-06',
            'credentials' => $memoizedProvider
        ]);
        $ssm = $sdk->createSsm();

        // settingsファイル内のすべての$$キーをリストアップ
        $keys = [];
        foreach ($this->settings as $settingArray) {
            foreach ($settingArray as $key => $value) {
                if (preg_match('/\$\$(.*)\$\$/', $value, $matches)) { // {{KEY_NAME}}か?
                    $keys[] = $matches[1];
                }
            }
        }
        try {
            $result = $ssm->getParameters([
                'Names' => $keys
            ]);
        } catch (AwsException $e) {
            var_dump($e);
            return false;  // TODO: 本当はException
        }

        var_dump($result);

        return false;
/*
        // jsonデータを展開し、apcuとローカルに格納する
        foreach (json_decode($secret, true) as $key => $value) {
            if (!apcu_exists($key)) {
                apcu_store($key, $value, static::SECRET_TTL);
            }
            $this->replacedText[$key] = $value;
        }

        if (!isset($this->replacedText[$original])) {
            return false; // TODO: 本当はException
        }

        return $this->replacedText[$original];
*/
    }
}

