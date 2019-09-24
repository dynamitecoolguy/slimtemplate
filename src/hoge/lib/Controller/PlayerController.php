<?php

namespace Hoge\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

use \PDO;

use Hoge\Model\Player;

class PlayerController
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

        $pdo = $this->container->get('db'); /** @var PDO $pdo */
        $stmt = $pdo->prepare('select * from player where player_id=:id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, Player::class);

        $player = $stmt->fetch();  /** @var Player|false $player */
        if (!$player) {
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode($player->toArray(), JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function post(Request $request, Response $response, array $args) : Response
    {
        $body = $request->getBody()->getContents();
        $input = json_decode($body);
        $name = $input->player_name;

        $pdo = $this->container->get('db'); /** @var PDO $pdo */
        $stmt = $pdo->prepare('insert into player(player_name) values(:name)');
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);

        $pdo->beginTransaction();
        $stmt->execute();
        $pdo->commit();

        return $response;
    }

    public function put(Request $request, Response $response, array $args) : Response
    {
        $body = $request->getBody()->getContents();
        $input = json_decode($body);
        $id = $args['id'];
        $name = $input->player_name;

        $pdo = $this->container->get('db'); /** @var PDO $pdo */
        $stmt = $pdo->prepare('update player set player_name=:name where player_id=:id');
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $pdo->beginTransaction();
        $stmt->execute();
        $pdo->commit();

        return $response;
    }
}
