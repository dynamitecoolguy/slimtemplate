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

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'settings' => [
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
        ]
    ],
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

$app->run();

