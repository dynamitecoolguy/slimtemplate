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

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->addPsr4('Hoge\\', __DIR__ . '/../lib');

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'settings' => [
        'db' => [
            'host' => 'mysql',
            'dbname' => 'userdb',
            'user' => 'scott',
            'password' => 'tiger'
        ]
    ],
    'db' => function (ContainerInterface $container) {
        $settings = $container->get('settings')['db'];
        $dsn = 'mysql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'];
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
# $app->addRoutingMiddleware();
# $app->addErrorMiddleware(true, true, true);

# $methodOverridingMiddleware = new \Slim\Middleware\MethodOverrideMiddleware();
# $app->add($methodOverridingMiddleware);

# $contentLengthMiddleware = new \Slim\Middleware\ContentLengthMiddleware();
# $app->add($contentLengthMiddleware);

# $outputBufferingMiddleware = new \Slim\Middleware\OutputBufferingMiddleware(\Slim\Middleware\OutputBufferingMiddleware::APPEND);
# $app->add($outputBufferingMiddleware);

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Hello, World!');
    return $response;
})
    ->add(new ExampleBeforeMiddleware())
    ->add(new ExampleAfterMiddleware());

$app->group('/user', function (RouteCollectorProxy $group) {
    $group->get('/{id}', PlayerController::class . ':get');
    $group->post('', PlayerController::class . ':post');
    $group->put('/{id}', PlayerController::class . ':put');
});

$app->run();

