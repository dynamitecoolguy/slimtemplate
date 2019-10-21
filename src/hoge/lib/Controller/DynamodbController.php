<?php

namespace Hoge\Controller;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Hoge\ApplicationSetting;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class DynamodbController
{
    /**
     * @var string
     */
    private $targetTable = null;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $dynamodb = $this->container->get('dynamodb'); /** @var DynamoDbClient $dynamodb */

        // dummy table
        try {
            $result = $dynamodb->describeTable([
                'TableName' => $this->getTable(),
            ]);
        } catch (DynamoDbException $ex) {
            $result = $dynamodb->createTable([
                'TableName' => $this->getTable(),
                'AttributeDefinitions' => [
                    [
                        'AttributeName' => 'key',
                        'AttributeType' => 'S'
                    ]
                ],
                'KeySchema' => [
                    [
                        'AttributeName' => 'key',
                        'KeyType' => 'HASH'
                    ]
                ],
                'ProvisionedThroughput' => [
                    'ReadCapacityUnits' => 1,
                    'WriteCapacityUnits' => 1
                ]
            ]);
        }
    }

    public function get(Request $request, Response $response, array $args) : Response
    {
        // parameters (id)
        $key = $args['key'];

        $dynamodb = $this->container->get('dynamodb'); /** @var DynamoDbClient $dynamodb */
        $marshaller = new Marshaler();
        $record = $marshaller->marshalItem(["key"=> $key]);

        try {
            $result = $dynamodb->getItem([
                'TableName' => $this->getTable(),
                'Key' => $record
            ]);
        } catch (DynamoDbException $ex) {
            return $response->withStatus(404);
        }
        if (!$result->hasKey('Item')) {
            return $response->withStatus(404);
        }
        $item = $result->get('Item');
        $found = $marshaller->unmarshalItem($item);
        $response->getBody()->write(json_encode($found));
        return $response;
    }

    public function post(Request $request, Response $response, array $args) : Response
    {
        $body = $request->getBody()->getContents();
        $input = json_decode($body);
        $key = $input->key;
        $value = $input->value;

        $dynamodb = $this->container->get('dynamodb'); /** @var DynamoDbClient $dynamodb */

        $marshaller = new Marshaler();
        $record = $marshaller->marshalItem(['key'=>$key, 'value'=>$value]);

        $dynamodb->putItem(['TableName'=>$this->getTable(), 'Item'=>$record]);
        $response->getBody()->write('OK');
        return $response;
    }

    private function getTable(): string
    {
        if (is_null($this->targetTable)) {
            $setting = ApplicationSetting::getInstance();
            $this->targetTable = $setting->getSettingValue('dynamodb', 'table');
        }
        return $this->targetTable;
    }
}
