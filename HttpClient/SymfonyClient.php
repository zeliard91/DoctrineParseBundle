<?php

namespace Redking\ParseBundle\HttpClient;

use Parse\HttpClients\ParseHttpable;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyClient implements ParseHttpable
{
    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var array
     */
    private array $responseHeaders = [];

    /**
     * @var int
     */
    private int $responseCode = 0;

    /**
     * @var string|null|null
     */
    private ?string $errorMessage = null;

    /**
     * @var string|null
     */
    private ?string $responseContentType = null;

    /**
     * @var ResponseInterface|null
     */
    private ?ResponseInterface $response = null;


    public function __construct(?HttpClientInterface $client = null)
    {
        if (null === $client) {
            $client = HttpClient::create();
            $this->client = new TraceableHttpClient($client);
        }
        $this->client = $client;

        $this->options['headers'] = [];
    }

    public function addRequestHeader($key, $value)
    {
        $this->options['headers'][$key] = $value;
    }

    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    public function getResponseStatusCode()
    {
        return $this->responseCode;
    }

    public function getResponseContentType()
    {
        return $this->responseContentType;
    }

    public function setConnectionTimeout($timeout)
    {
        $this->options['timeout'] = $timeout;
    }

    public function setTimeout($timeout)
    {
        $this->setConnectionTimeout($timeout);
    }

    public function setCAFile($caFile)
    {
        $this->options['cafile'] = $caFile;
    }

    public function setHttpOptions($httpOptions)
    {
        $this->options['extra']['curl'] = $httpOptions;
    }

    public function getErrorCode()
    {
        return $this->responseCode;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function setup(): void
    {
        
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $data
     * 
     * @return string
     */
    public function send($url, $method = 'GET', $data = array())
    {
        $options = $this->options;

        if ('GET' === $method && !empty($data)) {
            $options['query'] = $data;
        } elseif (!empty($data)) {
            $options['body'] = $data;
        }

        $this->response = $this->client->request($method, $url, $options);

        try {
            $content = $this->response->getContent();
        } catch (ClientException|ServerException $e) {
            $this->responseCode = $this->response->getStatusCode();
            $this->errorMessage = $this->response->getContent(false);

            return false;
        }

        $responseHeaders = $this->response->getHeaders(false);
        $this->responseContentType = $responseHeaders['Contect-Type'] ?? '';

        return $content;
    }
}