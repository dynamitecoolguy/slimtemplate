<?php

namespace Hoge\Test;

$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');
$loader->addPsr4('Hoge\\Test\\', __DIR__ . '/../test');

use Aws\DynamoDb\Exception\DynamoDbException;
use CURLFile;
use PHPUnit\Framework\TestCase;
use \PDO;

class RequestTest extends TestCase
{
    const URL_PREFIX = 'http://hoge.localhost/';

    protected static $ch;

    private static function setUpMySQL(): void
    {
        $dsn = 'mysql:host=127.0.0.1;port=13306;dbname=userdb';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, 'scott', 'tiger', $options);
        $stmt = $pdo->prepare('truncate player');
        $pdo->beginTransaction();
        $stmt->execute();
        $pdo->commit();
    }

    private static function setUpPostgreSQL(): void
    {
        $dsn = 'pgsql:host=127.0.0.1;port=15432;dbname=logdb';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, 'root', 'hogehoge', $options);
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
        $sdk = new \Aws\Sdk([
            'endpoint' => 'http://127.0.0.1:18000',
            'region' => 'ap-northeast-1',
            'version' => '2012-08-10',
            'credentials' => [
                'key' => 'dummy-key',
                'secret' => 'dummy-secret'
            ]
        ]);

        $dynamodb = $sdk->createDynamoDb();

        try {
            $result = $dynamodb->describeTable([
                'TableName' => 'hogehoge'
            ]);
            $dynamodb->deleteTable([
                'TableName' => 'hogehoge'
            ]);
        } catch (DynamoDbException $ex) {
        }
    }

    private static function setUpS3(): void
    {
        $sdk = new \Aws\Sdk([
            'endpoint' => 'http://127.0.0.1:19000',
            'region' => 'ap-northeast-1',
            'version' => '2006-03-01',
            'credentials' => [
                'key' => 'minio',
                'secret' => 'miniminio'
            ],
            //'bucket_endpoint' => true,
            'use_path_style_endpoint' => true
        ]);

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $sdk->createS3();
        $s3->deleteObject([
            'Bucket' => 'dummy',
            'Key' => 'hogefile.txt'
        ]);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$ch = curl_init();

        static::setUpMySQL();
        static::setUpPostgreSQL();
        static::setUpDynamoDb();
        static::setUpS3();
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
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX);
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $this->assertEquals($result, "BEFORE:Hello, World!:AFTER");
    }

    /**
     * @test
     */
    public function player(): void
    {
        /*
        $app->group('/player', function (RouteCollectorProxy $group) {
            $group->get('/{id}', PlayerController::class . ':get');
            $group->post('', PlayerController::class . ':post');
            $group->put('/{id}', PlayerController::class . ':put');
        });
        */

        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player/1');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['player_name' => 'PlayerName']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player/1');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $player = json_decode($result);
        $this->assertEquals($player->player_id, 1);
        $this->assertEquals($player->player_name, 'PlayerName');

        // put
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player/1');
        curl_setopt(static::$ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['player_name' => 'NewPlayerName']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again again
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player/1');
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
        /*
        $app->group('/player_created', function (RouteCollectorProxy $group) {
            $group->get('/{id}', PlayerCreatedLogController::class . ':get');
            $group->get('', PlayerCreatedLogController::class . ':list');
        });
        */

        // get
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player_created/2');
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
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'player_created');
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
        /*
        $app->group('/redis', function (RouteCollectorProxy $group) {
            $group->get('/{key}', RedisController::class . ':get');
            $group->post('', RedisController::class . ':post');
            $group->put('/{key}', RedisController::class . ':put');
            $group->delete('/{key}', RedisController::class . ':delete');
        });
        */

        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'redis/hoge');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'redis');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['key' => 'hoge', 'value' => 'hogehoge']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'redis/hoge');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $this->assertEquals($result, 'hogehoge');

        // delete
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'redis/hoge');
        curl_setopt(static::$ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again twice
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'redis/hoge');
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
        /*
        $app->group('/dynamodb', function (RouteCollectorProxy $group) {
            $group->get('/{key}', DynamodbController::class . ':get');
            $group->post('', DynamodbController::class . ':post');
        });
        */
        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'dynamodb/hoge');
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 404);

        // post
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'dynamodb');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, json_encode(['key' => 'hoge', 'value' => 'hogehoge']));
        curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'dynamodb/hoge');
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
        /*
        $app->group('/storage', function (RouteCollectorProxy $group) {
            $group->get('/{filename}', StorageController::class . ':get');
            $group->post('', StorageController::class . ':post');
        });
         */
        // get but not found
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'storage/hogefile.txt');
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

        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'storage');
        curl_setopt(static::$ch, CURLOPT_POST, true);
        curl_setopt(static::$ch, CURLOPT_HTTPHEADER, ['Contexnt-Type: multipart/form-data']);
        curl_setopt(static::$ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($result, 'http://storage:9000/dummy/hogefile.txt');

        // get again
        curl_setopt(static::$ch, CURLOPT_URL, self::URL_PREFIX . 'storage/hogefile.txt');
        curl_setopt(static::$ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec(static::$ch);
        $info = curl_getinfo(static::$ch);
        curl_reset(static::$ch);
        $this->assertEquals($info['http_code'], 200);
        $this->assertEquals($result, 'Hello, World!');
    }
}
