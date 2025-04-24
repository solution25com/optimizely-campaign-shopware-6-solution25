<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class OptimizelyAPI
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Client
     */
    private $client;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;

        $this->client = new Client([
            'base_uri' => 'https://api.campaign.episerver.net/http/',
            'timeout' => 10
        ]);
    }

    public function request(AbstractOptimizelyRequest $optimizelyRequest): ?ResponseInterface
    {
        $response = null;
        try {
            $url = $optimizelyRequest->getEndpoint()
                . '/' . $optimizelyRequest->getAuthCode()
                . '/' . $optimizelyRequest->getMethod();



            $response = $this->client->send(new Request('POST', $url, [
                'Accept' => '*/*',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Connection' => 'keep-alive'
            ], http_build_query($optimizelyRequest->getData())));

            $responseContent = $response->getBody()->getContents();

            if (strpos(strtolower($responseContent), 'ok') === false
                && strpos(strtolower($responseContent), 'enqueued') === false) {
                $optimizelyRequest->saveRequestToErrorQueue($responseContent);
            } else {
                $optimizelyRequest->removeFromErrorQueue();
            }
        } catch (ClientException|ConnectException|RequestException|ServerException $exception) {
            $response = $exception->getResponse();
            $errorStatus = 'unknown';
            if (!is_null($response)) {
                $errorStatus = $response->getBody()->getContents();
            }

            $optimizelyRequest->saveRequestToErrorQueue($errorStatus);
        } catch (\Exception $exception) {
            $optimizelyRequest->saveRequestToErrorQueue($exception->getMessage());
        }

        return $response;
    }
}
