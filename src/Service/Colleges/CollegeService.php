<?php
declare(strict_types=1);

namespace App\Service\Colleges;

use App\Entity\College;
use App\Repository\CollegeRepository;
use App\Service\Colleges\Handlers\CollegeDataHandler;
use App\Service\Colleges\Handlers\CollegeRequestHandler;
use App\Service\Http\HttpRequest;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CollegeService
{
    public const OPTION_SURFACE_DATA = 'surface';
    public const OPTION_DETAILED_INFO = 'detailed';
    public const OPTION_DETAILED_IF_NEW = 'new';

    private const DOMAIN = 'www.princetonreview.com';
    private const COLLEGES_URL = '/college-search?ceid=cp-1022984';
    private const START_PAGE_NUMBER = 1;

    private const MAX_CONCURRENT_REQUESTS_NUMBER = 200;

    private CollegesParser $parser;
    private CollegeDataHandler $dataHandler;
    private CollegeRequestHandler $requestHandler;
    private LoggerInterface $logger;

    public function __construct(CollegeRepository $collegeRepository, ?LoggerInterface $logger)
    {
        $this->parser = new CollegesParser();
        $this->dataHandler = new CollegeDataHandler($collegeRepository);
        $this->requestHandler = new CollegeRequestHandler($logger);

        $this->logger = $logger ?? new Logger('colleges');
    }

    /**
     * @param string $option
     * @param int|null $startPage
     * @param int|null $endPage
     * @return array
     * @throws TransportExceptionInterface
     * @throws HttpExceptionInterface
     */
    public function getColleges(string $option = self::OPTION_SURFACE_DATA, ?int $startPage = self::START_PAGE_NUMBER, ?int $endPage = PHP_INT_MAX): array
    {
        $startPage = $startPage ?? self::START_PAGE_NUMBER;
        $endPage = $endPage ?? PHP_INT_MAX;
        if ($startPage < 1 || $endPage < 1)
        {
            throw new RuntimeException('Invalid page number. (Must be > 0)');
        }

        if (!in_array($option, [self::OPTION_SURFACE_DATA, self::OPTION_DETAILED_INFO, self::OPTION_DETAILED_IF_NEW]))
        {
            throw new RuntimeException('Unknown option: \'' . $option . '\'');
        }

        $url = HttpRequest::HTTPS . '://' . self::DOMAIN . self::COLLEGES_URL;
        $startPageUrl = $url . "&page=$startPage";
        $response =  $this->requestHandler->getResponse($startPageUrl);

        $maxPageNumber = $this->parser->getMaxPageNumber($response->getContent());
        if ($maxPageNumber < $endPage)
        {
            $endPage = $maxPageNumber;
        }

        $responses = array_merge([$response], $this->requestHandler->getCollegeListResponses($url, $startPage, $endPage));
        $collegesData = $this->getCollegesSurfaceDataFromResponses($responses);
        [$newColleges, $updatedColleges] =  $this->dataHandler->handleCollegesBySurfaceData($collegesData, self::DOMAIN);

        if ($option === self::OPTION_DETAILED_IF_NEW)
        {
            $this->getDetailedCollegesInfo($newColleges);
        }

        if ($option === self::OPTION_DETAILED_INFO)
        {
            $this->getDetailedCollegesInfo(array_merge($newColleges, $updatedColleges));
        }

        return [$newColleges, $updatedColleges];
    }

    /**
     * @param College[] $colleges
     * @return College[]
     */
    public function getDetailedCollegesInfo(array $colleges): array
    {
        $allRequestCount = count($colleges);
        $maxConcurrentRequests = self::MAX_CONCURRENT_REQUESTS_NUMBER;
        for ($offset = 0; $offset < $allRequestCount; $offset += $maxConcurrentRequests)
        {
            $currentColleges = array_slice($colleges, $offset, $maxConcurrentRequests);
            $responses = $this->requestHandler->getCollegePagesResponses($currentColleges);
            $this->processDetailedInfoFromResponses($responses, $currentColleges);
        }

        return $colleges;
    }

    /**
     * @param ResponseInterface[] $responses
     * @return array
     */
    private function getCollegesSurfaceDataFromResponses(array $responses): array
    {
        $collegesData = [];
        foreach ($responses as $response)
        {
            try
            {
                $html = $response->getContent();
                foreach ($this->parser->getDataFromCollegeList($html) as $data)
                {
                    $collegesData[] = $data;
                }
            }
            catch (ExceptionInterface $e)
            {
                $this->logger->error('Error when requesting a list of colleges: ' . $response->getInfo('url') . "\n" . $e->getMessage());
                continue;
            }
        }

        return $collegesData;
    }

    /**
     * @param ResponseInterface[] $responses
     * @param College[] $colleges
     * @return void
     */
    private function processDetailedInfoFromResponses(array $responses, array $colleges): void
    {
        foreach ($responses as $index => $response)
        {
            if (!$response)
            {
                continue;
            }

            try
            {
                $data = $this->parser->getDetailedInfoFromCollegePage($response->getContent());
                $this->dataHandler->addDetailedInfoToCollege($colleges[$index], $data);
            }
            catch (ExceptionInterface $e)
            {
                $this->logger->error('Error when requesting the college page: ' . $colleges[$index]->getName() . "\n" . $e->getMessage());
                continue;
            }
        }
    }
}