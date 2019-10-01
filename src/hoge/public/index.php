<?php

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use DI\ContainerBuilder;

use Hoge\ExampleAfterMiddleware;
use Hoge\ExampleBeforeMiddleware;
use Hoge\Controller\PlayerController;
use Hoge\Controller\PlayerCreatedLogController;
use Hoge\Controller\RedisController;
use Hoge\Controller\DynamodbController;
use Hoge\Controller\StorageController;

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');

$settings = require __DIR__ . '/../../settings.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'settings' => $settings,
    'userdb' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['userdb'];
        $dsn = 'mysql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        return new PDO($dsn, $settings['user'], $settings['password'], $options);
    },
    'logdb' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['logdb'];
        $dsn = 'pgsql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        return new PDO($dsn, $settings['user'], $settings['password'], $options);
    },
    'redis' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['redis'];
        $redis = new Redis();
        $redis->connect($settings['host']);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

        return $redis;
    },
    'dynamodb' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['dynamodb'];

        $sdk = new Aws\Sdk([
            'endpoint' => $settings['endpoint'],
            'region' => $settings['region'],
            'version' => '2012-08-10',
            'credentials' => [
                'key' => 'dummy-key',
                'secret' => 'dummy-secret'
            ]
        ]);

        $dynamodb = $sdk->createDynamoDb();
        return $dynamodb;
    },
    'storage' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['storage'];
        $sdk = new Aws\Sdk([
            'endpoint' => $settings['endpoint'],
            'region' => $settings['region'],
            'version' => '2006-03-01',
            'credentials' => [
                'key' => 'minio',
                'secret' => 'miniminio'
            ],
            //'bucket_endpoint' => true,
            'use_path_style_endpoint' => true
        ]);
        $s3 = $sdk->createS3();
        return $s3;
    }
]);
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware
$contentLengthMiddleware = new \Slim\Middleware\ContentLengthMiddleware();
$app->add($contentLengthMiddleware);

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Hello, World!');
    return $response;
})
    ->add(new ExampleBeforeMiddleware())
    ->add(new ExampleAfterMiddleware());

$app->group('/player', function (RouteCollectorProxy $group) {
    $group->get('/{id}', PlayerController::class . ':get');
    $group->post('', PlayerController::class . ':post');
    $group->put('/{id}', PlayerController::class . ':put');
});

$app->group('/player_created', function (RouteCollectorProxy $group) {
    $group->get('/{id}', PlayerCreatedLogController::class . ':get');
    $group->get('', PlayerCreatedLogController::class . ':list');
});

$app->group('/redis', function (RouteCollectorProxy $group) {
    $group->get('/{key}', RedisController::class . ':get');
    $group->post('', RedisController::class . ':post');
    $group->put('/{key}', RedisController::class . ':put');
    $group->delete('/{key}', RedisController::class . ':delete');
});

$app->group('/dynamodb', function (RouteCollectorProxy $group) {
    $group->get('/{key}', DynamodbController::class . ':get');
    $group->post('', DynamodbController::class . ':post');
});

$app->group('/storage', function (RouteCollectorProxy $group) {
    $group->get('/{filename}', StorageController::class . ':get');
    $group->post('', StorageController::class . ':post');
});

$app->run();

