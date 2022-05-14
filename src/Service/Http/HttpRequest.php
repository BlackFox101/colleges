<?php
declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpRequest
{
    /**
     * @throws TransportExceptionInterface
     */
    public static function getRequest(string $url): ResponseInterface
    {
        return HttpClient::create()->request('GET', $url);
    }
}