<?php

namespace Hoge\Controller;

use Aws\S3\Exception\S3Exception;
use Hoge\ApplicationSetting;
use PharIo\Manifest\Application;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class StorageController
{
    private $targetBucket = null;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $this->container->get('storage');
        try {
            $s3->headBucket(['Bucket' => $this->getBucket()]);
        } catch (S3Exception $ex) {
            $errorCode = $ex->getAwsErrorCode();
            if ($errorCode === 'NotFound') {
                $s3->createBucket([
                    'Bucket' => $this->getBucket(),
                    'CreateBucketConfiguration' => [
                        'LocationConstraint' => 'ap-northeast-1'
                    ]
                ]);
            }
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        // parameters (id)
        $key = $args['filename'];

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $this->container->get('storage');

        try {
            $result = $s3->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $key
            ]);
        } catch (S3Exception $ex) {
            return $response->withStatus(404);
        }

        $body = $result->get('Body');
        $newResponse = $response->withBody($body);
        return $newResponse;
    }

    public function post(Request $request, Response $response, array $args): Response
    {
        $files = $request->getUploadedFiles();
        /** @var \Psr\Http\Message\UploadedFileInterface $uploadedFile */
        $uploadedFile = $files['upload'];

        $err = $uploadedFile->getError();
        if ($err !== 0) {
            return $response->withStatus(500);
        }
        $clientFileName = $uploadedFile->getClientFilename();

        /** @var \Aws\S3\S3Client $s3 */
        $s3 = $this->container->get('storage');

        $result = $s3->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $clientFileName,
            'Body' => $uploadedFile->getStream()
        ]);
        $url = $result->get('ObjectURL');

        $response->getBody()->write($url);
        return $response;
    }

    private function getBucket(): string
    {
        if (is_null($this->targetBucket)) {
            $setting = ApplicationSetting::getInstance();
            $this->targetBucket = $setting->getSettingValue('storage', 'bucket');
        }
        return $this->targetBucket;
    }
}


