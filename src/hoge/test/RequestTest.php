<?php

namespace Hoge\Test;

$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');
$loader->addPsr4('Hoge\\Test\\', __DIR__ . '/../test');

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\S3\Exception\S3Exception;
use CURLFile;
use PHPUnit\Framework\TestCase;
use \PDO;

class RequestTest extends TestCase
{
    protected static $ch;

    private static function getSettingArray(): array
    {
        switch (getenv('environment')) {
            case 'development':
                return static::getSettingArrayDevelopment();
            case 'devaws':
                return static::getSettingArrayDevaws();
        }
        return static::getSettingArrayLocal();
    }

    private static function getSettingArrayLocal(): array
    {
        $setting = yaml_parse_file(__DIR__ . '/../../../compose/web_hoge/settings-local.yml');

        $setting['prefix'] = 'http://hoge.localhost/';
        $setting['userdb']['host'] = '127.0.0.1';
        $setting['userdb']['port'] = 13306;
        $setting['logdb']['host'] = '127.0.0.1';
        $setting['logdb']['port'] = 15432;
        $setting['dynamodb']['endpoint'] = 'http://127.0.0.1:18000';
        $setting['storage']['endpoint'] = 'http://127.0.0.1:19000';

        return $setting;
    }

    private static function getSettingArrayDevelopment(): array
    {
        $setting = yaml_parse_file(__DIR__ . '/../../../compose/web_hoge/settings-development.yml');
        $ip = '13.113.92.135';

        $setting['prefix'] = 'http://' . $ip . '/';
        $setting['userdb']['host'] = $ip;
        $setting['userdb']['port'] = 13306;
        $setting['logdb']['host'] = $ip;
        $setting['logdb']['port'] = 15432;
        $setting['dynamodb']['endpoint'] = 'http://' . $ip . ':18000';
        $setting['storage']['endpoint'] = 'http://' . $ip . ':19000';

        return $setting;
    }

    private static function getSettingArrayDevaws(): array
    {
        $setting = yaml_parse_file(__DIR__ . '/../../../compose/web_hoge/settings-devaws.yml');
        $ip = '13.113.92.135';

        $setting['prefix'] = 'http://' . $ip . '/';
        $setting['userdb']['host'] = $ip;
        $setting['userdb']['port'] = 13306;
        $setting['logdb']['host'] = $ip;
        $setting['logdb']['port'] = 15432;
        $setting['dynamodb']['endpoint'] = 'http://' . $ip . ':18000';
        $setting['storage']['endpoint'] = 'http://' . $ip . ':19000';

        return $setting;
    }

    private static function setUpMySQL(): void
    {
        $setting = static::getSettingArray()['userdb'];
        if ($setting === false) {
            return;
        }

        $dsn = 'mysql:host=' . $setting['host'] . ';port=' . (string)$setting['port'] . ';dbname=' . $setting['dbname'];
        echo "DSN=$dsn\n";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, $setting['user'], $setting['password'], $options);
        $stmt = $pdo->prepare('truncate player');
        $pdo->beginTransaction();
        $stmt->execute();
        $pdo->commit();
        echo "MySQL done$dsn\n";
    }

    private static function setUpPostgreSQL(): void
    {
        $setting = static::getSettingArray()['logdb'];
        if ($setting === false) {
            return;
        }

        $dsn = 'pgsql:host=' . $setting['host']. ';port=' . (string)$setting['port']. ';dbname=' . $setting['dbname'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, $setting['user'], $setting['password'], $options);
        $stmt = $pdo->prepare('truncate player_created_log');
        $pdo->beginTransaction();
        $stmt->execute();
        $pdo->commit();

        $stmt2 = $pdo->prepare("insert into player_created_log(player_id, created_at) values(1, '2019-10-01 12:34:56'),(2, '2019-10-03 15:00:00')");
        $pdo->beginTransaction();
        $stmt2->execute();
        $pdo->commit();
    }

    private static function setUpDynamoDb(): void
    {
        $setting = static::getSettingArray()['dynamodb'];
        if ($setting === false) {
            return;
        }

        $sdk = new \Aws\Sdk([
            'endpoint' => $setting['endpoint'],
            'region' => 'ap-northeast-1',
            'version' => '2012-08-10',
            'credentials' => [
                'key' => $setting['key'],
                'secret' => $setting['secret']
            ]
        ]);

        $dynamodb = $sdk->createDynamoDb();

        try {
            $result = $dynamodb->describeTable([
                'TableName' => $setting['table']
            ]);
            $dynamodb->deleteTable([
                'TableName' => $setting['table']
            ]);
        } catch (DynamoDbException $ex) {
        }
    }

    private static function setUpS3(): void
    {
        $setting = static::getSettingArray()['storage'];
        if ($setting === false) {
            return;
        }

        $sdk = new \Aws\Sdk([
            'endpoint' => $setting['endpoint'],
            'region' => 'ap-northeast-1',
            'version' => '2006-03-01',
            'credentials' => [
                'key' => $setting['key'],
                'secret' => $setting['secret']
            ],
            //'bucket_endpoint' => true,
            'use_path_style_endpoint' => true
        ]);

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $sdk->createS3();
        try {
            $s3->createBucket([
                'Bucket' => $setting['bucket']
            ]);
        } catch (S3Exception $e) {
        }
        $s3->deleteObject([
            'Bucket' => $setting['bucket'],
            'Key' => 'hogefile.txt'
        ]);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$ch = curl_init();
echo "setUpBeforeClass:BEGIN\n";
        static::setUpMySQL();
        static::setUpPostgreSQL();
        static::setUpDynamoDb();
        static::setUpS3();
echo "setUpEndClass:BEGIN\n";
    }

    public static function tearDownAfterClass(): void
    {
        curl_close(static::$ch);
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        curl_reset(static::$ch);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function top(): void
    {
        $prefix = static::getSettingArray()['prefix'];
        curl_setopt(static::$ch, CURLOPT_URL, $prefix);
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $this->assertEquals($result, "BEFORE:Hello, World!:AFTER");
    }

    /**
     * @test
     */
    public function player(): void
    {
        $prefix = static::getSettingArray()['prefix'];

        /*
        $app->group('/player', function (RouteCollectorProxy $group) {
            $group->get('/{id}', PlayerController::class . ':get');
            $group->post('', PlayerController::class . ':post');
            $group->put('/{id}', PlayerController::class . ':put');
        });
        */

        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player/1');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['player_name' => 'PlayerName']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player/1');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $player = json_decode($result);
        $this->assertEquals($player->player_id, 1);
        $this->assertEquals($player->player_name, 'PlayerName');

        // put
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player/1');
        curl_setopt(static::$ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['player_name' => 'NewPlayerName']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again again
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player/1');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $player = json_decode($result);
        $this->assertEquals($player->player_id, 1);
        $this->assertEquals($player->player_name, 'NewPlayerName');
    }

    /**
     * @test
     */
    public function playerCreated(): void
    {
        $prefix = static::getSettingArray()['prefix'];

        /*
        $app->group('/player_created', function (RouteCollectorProxy $group) {
            $group->get('/{id}', PlayerCreatedLogController::class . ':get');
            $group->get('', PlayerCreatedLogController::class . ':list');
        });
        */

        // get
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player_created/2');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $playerCreatedList = json_decode($result);
        $playerCreated = $playerCreatedList[0];
        $this->assertEquals($playerCreated->player_id, 2);
        $this->assertEquals($playerCreated->created_at, '2019-10-03 15:00:00');

        // list
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'player_created');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $playerCreatedList = json_decode($result);
        foreach ($playerCreatedList as $playerCreated) {
            $this->assertTrue($playerCreated->player_id === 1 || $playerCreated->player_id === 2);
            if ($playerCreated->player_id == 1) {
                $this->assertEquals($playerCreated->created_at, '2019-10-01 12:34:56');
            } else {
                $this->assertEquals($playerCreated->created_at, '2019-10-03 15:00:00');
            }
        }
    }

    /**
     * @test
     */
    public function redis(): void
    {
        $prefix = static::getSettingArray()['prefix'];

        /*
        $app->group('/redis', function (RouteCollectorProxy $group) {
            $group->get('/{key}', RedisController::class . ':get');
            $group->post('', RedisController::class . ':post');
            $group->put('/{key}', RedisController::class . ':put');
            $group->delete('/{key}', RedisController::class . ':delete');
        });
        */

        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'redis/hoge');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'redis');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['key' => 'hoge', 'value' => 'hogehoge']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'redis/hoge');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $this->assertEquals($result, 'hogehoge');

        // delete
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'redis/hoge');
        curl_setopt(static::$ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again twice
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'redis/hoge');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);
    }

    /**
     * @test
     */
    public function dynamodb(): void
    {
        $prefix = static::getSettingArray()['prefix'];

        /*
        $app->group('/dynamodb', function (RouteCollectorProxy $group) {
            $group->get('/{key}', DynamodbController::class . ':get');
            $group->post('', DynamodbController::class . ':post');
        });
        */
        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'dynamodb/hoge');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'dynamodb');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['key' => 'hoge', 'value' => 'hogehoge']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'dynamodb/hoge');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $record = json_decode($result);
        $this->assertEquals($record->key, 'hoge');
        $this->assertEquals($record->value, 'hogehoge');
    }

    /**
     * @test
     */
    public function storage(): void
    {
        $prefix = static::getSettingArray()['prefix'];

        /*
        $app->group('/storage', function (RouteCollectorProxy $group) {
            $group->get('/{filename}', StorageController::class . ':get');
            $group->post('', StorageController::class . ':post');
        });
         */
        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'storage/hogefile.txt');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post file
        $tmpFile = tmpfile();
        $tmpFileName = stream_get_meta_data($tmpFile)['uri'];
        fwrite($tmpFile, 'Hello, World!');

        $curlFile = new CURLFile($tmpFileName, 'text/plain', 'hogefile.txt');
        $data = ['upload' => $curlFile];

        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'storage');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($result, 'http://storage:9000/dummy/hogefile.txt');

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, $prefix . 'storage/hogefile.txt');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $this->assertEquals($result, 'Hello, World!');
    }
}
