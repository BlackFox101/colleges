<?php
declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpRequest
{
    public const HTTPS = 'https';

    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getResponse(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url);
    }
}