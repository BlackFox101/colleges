<?php
declare(strict_types=1);

namespace App\Service\Http;

use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class HttpRequest
{
    private const SUCCESS_STATUS_CODE = 200;

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public static function getHtml(string $url): string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() !== self::SUCCESS_STATUS_CODE)
        {
            throw new RuntimeException('Invalid server response. Status code: ' . $response->getStatusCode());
        }

        return $response->getContent();
    }
}