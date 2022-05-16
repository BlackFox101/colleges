<?php
declare(strict_types=1);

namespace App\Service\Colleges\Handlers;

use App\Entity\College;
use App\Service\Http\HttpRequest;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CollegeRequestHandler
{
    private HttpRequest $httpRequest;
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger)
    {
        $this->httpRequest = new HttpRequest();
        $this->logger = $logger ?? new Logger('requestHandler');
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getResponse(string $url): ResponseInterface
    {
        return $this->httpRequest->getResponse($url);
    }

    /**
     * @param string $url
     * @param int $startPage
     * @param int $endPage
     * @return ResponseInterface[]
     */
    public function getCollegeListResponses(string $url, int $startPage, int $endPage): array
    {
        $responses = [];
        for ($page = $startPage + 1; $page <= $endPage; $page++)
        {
            try
            {
                $responses[] = $this->httpRequest->getResponse($url . "&page=$page");
            }
            catch (TransportExceptionInterface)
            {
                break;
            }
        }

        return $responses;
    }

    /**
     * @param College[] $colleges
     * @return ResponseInterface[]|null[]
     */
    public function getCollegePagesResponses(array $colleges): array
    {
        $responses = [];
        foreach ($colleges as $college)
        {
            try
            {
                $responses[] = $this->httpRequest->getResponse($college->getCollegePageUrl());
            }
            catch (TransportExceptionInterface $e)
            {
                $this->logger->error('Error when requesting the college page: ' . $college->getName() . "\n" . $e->getMessage());
                $responses[] = null;
            }
        }

        return $responses;
    }
}