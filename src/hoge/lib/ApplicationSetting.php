<?php

namespace Hoge;

use Aws\Credentials\CredentialProvider;
use Aws\Exception\AwsException;
use Aws\Sdk;

/**
 * Class ApplicationSetting
 * @package Hoge
 */
class ApplicationSetting
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
     * @var ApplicationSetting
     */
    private static $self = null;

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
     * @return ApplicationSetting インスタンスを取得
     */
    public static function getInstance(): ApplicationSetting
    {
        if (is_null(static::$self)) {
            static::$self = new static();
        }
        return static::$self;
    }

    /**
     * ApplicationSetting constructor.
     */
    protected function __construct()
    {
        $this->settings = yaml_parse_file(static::SETTINGS_YAML);
    }

    /**
     * 指定されたカテゴリ内のキーの値を取得する
     * @param string $category
     * @param string $key
     * @return mixed
     */
    public function getSettingValue(string $category, string $key)
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

        // SystemManagerのパラメータストアから値を一斉に取得
        try {
            $result = $ssm->getParameters([
                'Names' => $keys
            ]);
        } catch (AwsException $e) {
            return false;  // TODO: 本当はException
        }

        foreach ($result['Parameters'] as $parameter) {
            $name = $parameter['Name'];
            $value = $parameter['Value'];
            if (!apcu_exists($name)) {
                apcu_store($name, $value, static::PARAMETER_TTL);
            }
            $this->replacedText[$name] = $value;
        }

        if (!isset($this->replacedText[$original])) {
            return false; // TODO: 本当はException
        }
        return $this->replacedText[$original];
    }
}
