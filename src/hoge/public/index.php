<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

use Hoge\ExampleAfterMiddleware;
use Hoge\ExampleBeforeMiddleware;
use Hoge\MyContainerBuilder;
use Hoge\Controller\PlayerController;
use Hoge\Controller\PlayerCreatedLogController;
use Hoge\Controller\RedisController;
use Hoge\Controller\DynamodbController;
use Hoge\Controller\StorageController;

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');

$containerBuilder = new MyContainerBuilder();
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware
$contentLengthMiddleware = new \Slim\Middleware\ContentLengthMiddleware();
$app->add($contentLengthMiddleware);

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Hello, World2!');
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

