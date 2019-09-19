<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use Hoge\ExampleAfterMiddleware;
use Hoge\ExampleBeforeMiddleware;

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');

$app = AppFactory::create();

$app->add(new ExampleBeforeMiddleware());
$app->add(new ExampleAfterMiddleware());

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Hello, World!');
    return $response;
});

$app->run();

