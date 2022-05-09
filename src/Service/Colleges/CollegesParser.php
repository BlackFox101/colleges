<?php
declare(strict_types=1);

namespace App\Service\Colleges;

use App\Service\Http\HttpRequest;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class CollegesParser
{
    private bool $isNextPageExist = true;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getDataFromCollegesList(int $page, string $html): array
    {
        $crawler = new Crawler($html);
        $collegesRows = $crawler->filter('.row .vertical-padding');
        if (count($collegesRows) <= 0)
        {
            $this->isNextPageExist = false;
            return [];
        }

        $collegesData = [];
        foreach ($collegesRows as $index => $row)
        {
            $node = new Crawler($row);
            try
            {
                $collegesData[] =  $this->getCollegeData($node);
            }
            catch (Exception $e)
            {
                $this->logger->error("Page: $page, College: $index;\n" . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine());
            }
        }

        return $collegesData;
    }

    public function isNextPageExist(): bool
    {
        return $this->isNextPageExist;
    }

    private function getCollegeData(Crawler $node): array
    {
        if ($node->count() < 0)
        {
            return [];
        }

        $collegePageHref = $node->filter('a')->attr('href');
        $infoNode = $node->filter('h2');
        $data[CollegeService::NAME] = $infoNode->text();

        $locationNode = $infoNode->nextAll()->filter('.location');
        if ($locationNode->count() > 0)
        {
            [$city, $state] = explode(',', $locationNode->text());

            $data[CollegeService::CITY] = $city;
            $data[CollegeService::STATE] = trim($state);
        }
        $image = $node->selectImage($data[CollegeService::NAME])->filter('.school-image-large, .school-image');
        if ($image->count() > 0 )
        {
            $data[CollegeService::IMAGE_URL] = CollegeService::HTTPS . ':' . $image->attr('src');
        }

        $collegePageUrl = CollegeService::HTTPS . '://' . CollegeService::DOMAIN . $collegePageHref;
        try
        {
            $html = HttpRequest::getHtml($collegePageUrl);
            return array_merge($data, $this->getDataFromCollegePage($html));
        }
        catch (ExceptionInterface $e)
        {
            $this->logger->error("Error when getting the college page.\n"
                . 'Page: ' . $collegePageUrl
                . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine());
        }

        return $data;
    }

    private function getDataFromCollegePage(string $html): array
    {
        $crawler = new Crawler($html);

        $addressNode = $crawler->filter('div[itemprop="address"] > span');
        $address = '';
        foreach ($addressNode as $node)
        {
            $span = new Crawler($node);
            $address .=  ' ' . $span->text();
        }
        $data[CollegeService::ADDRESS] = $address;

        $websiteNode = $crawler->filter('div[itemprop="address"] > a');
        if ($websiteNode->count() > 0)
        {
            $data[CollegeService::SITE] = $websiteNode->attr('href');
        }

        $schoolDataNode = $crawler->filter('.school-contacts');
        $contactNode = $schoolDataNode->children()->eq(0);
        $rowsNode = $contactNode->children()->filter('.row');

        foreach ($rowsNode as $row)
        {
            $crawler = new Crawler($row);
            $dataNodes = $crawler->children();

            if ($dataNodes->eq(0)->text() === 'Phone')
            {
                $data[CollegeService::PHONE] = $dataNodes->eq(1)->text();
            }
        }

        return $data;
    }
}