<?php

namespace Hoge\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

use \Redis;

class RedisController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(Request $request, Response $response, array $args) : Response
    {
        // parameters (id)
        $key = $args['key'];

        $redis = $this->container->get('redis'); /** @var Redis $redis */
        $value = $redis->get($key);
        if ($value === false) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($value);
        return $response;
    }

    public function post(Request $request, Response $response, array $args) : Response
    {
        $body = $request->getBody()->getContents();
        $input = json_decode($body);
        $key = $input->key;
        $value = $input->value;

        $redis = $this->container->get('redis'); /** @var Redis $redis */
        $result = $redis->set($key, $value, 30); // とりあえず30sec固定

        $response->getBody()->write($result ? 'OK' : 'Failure');
        return $response;
    }

    public function put(Request $request, Response $response, array $args) : Response
    {
        $body = $request->getBody()->getContents();
        $input = json_decode($body);
        $key = $args['key'];
        $value = $input->value;

        $redis = $this->container->get('redis'); /** @var Redis $redis */
        $result = $redis->set($key, $value, ['xx', 'ex'=>30]); // とりあえず30sec固定

        $response->getBody()->write($result ? 'OK' : 'Failure');
        return $response;
    }

    public function delete(Request $request, Response $response, array $args) : Response
    {
        $key = $args['key'];

        $redis = $this->container->get('redis'); /** @var Redis $redis */
        $result = $redis->del($key);

        $response->getBody()->write($result ? 'OK' : 'Failure');
        return $response;
    }
}
