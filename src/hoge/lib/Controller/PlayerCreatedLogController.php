<?php

namespace Hoge\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

use \PDO;

use Hoge\Model\PlayerCreatedLog;

class PlayerCreatedLogController
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
        $id = $args['id'];

        $pdo = $this->container->get('logdb'); /** @var PDO $pdo */
        $stmt = $pdo->prepare('select * from player_created_log where player_id=:id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, PlayerCreatedLog::class);

        $logList = $stmt->fetchAll();  /** @var PlayerCreatedLog[] $logList */

        $logArray = array_map(
            function ($log) { /** @var PlayerCreatedLog $log */
                return $log->toArray();
            },
            $logList
        );

        $response->getBody()->write(json_encode($logArray, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function list(Request $request, Response $response, array $args) : Response
    {
        $pdo = $this->container->get('logdb'); /** @var PDO $pdo */
        $stmt = $pdo->prepare('select * from player_created_log');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, PlayerCreatedLog::class);

        $logList = $stmt->fetchAll();  /** @var PlayerCreatedLog[] $logList */

        $logArray = array_map(
            function ($log) { /** @var PlayerCreatedLog $log */
                return $log->toArray();
            },
            $logList
        );

        $response->getBody()->write(json_encode($logArray, JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
