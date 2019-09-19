<?php

namespace Hoge;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;


class ExampleBeforeMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        $existingContent = (string) $response->getBody();

        $newResponse = new Response();
        $newResponse->getBody()->write('BEFORE:' .  $existingContent);

        return $newResponse;
    }
}
