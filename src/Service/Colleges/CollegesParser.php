<?php
declare(strict_types=1);

namespace App\Service\Colleges;

use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\DomCrawler\Crawler;

class CollegesParser
{
    private const NAME = 'name';
    private const CITY = 'city';
    private const STATE = 'state';
    private const ADDRESS = 'address';
    private const PHONE = 'phone';
    private const SITE = 'site';
    private const IMAGE_URL = 'image_url';
    private const COLLEGE_PAGE_URL = 'college_page_url';

    #[ArrayShape([
        self::NAME => 'string',
        self::CITY => 'string',
        self::STATE => 'string',
        self::IMAGE_URL => 'string',
        self::COLLEGE_PAGE_URL => 'string'
    ])]
    /**
     * @param string $html
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDataFromCollegeList(string $html): array
    {
        $crawler = new Crawler($html);
        $collegesRows = $crawler->filter('.row .vertical-padding');
        if (count($collegesRows) <= 0)
        {
            return [];
        }

        $collegesData = [];
        foreach ($collegesRows as $row)
        {
            $node = new Crawler($row);
            $collegesData[] =  $this->getCollegeInfo($node);
        }

        return $collegesData;
    }

    #[ArrayShape([
        self::ADDRESS => 'string',
        self::PHONE => 'string',
        self::SITE => 'string'
    ])]
    /**
     * @param string $html
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDetailedInfoFromCollegePage(string $html): array
    {
        $crawler = new Crawler($html);

        $addressNode = $crawler->filter('div[itemprop="address"] > span');
        $address = '';
        foreach ($addressNode as $node)
        {
            $span = new Crawler($node);
            $address .=  ' ' . $span->text();
        }
        $data[self::ADDRESS] = $address;

        $websiteNode = $crawler->filter('div[itemprop="address"] > a');
        if ($websiteNode->count() > 0)
        {
            $data[self::SITE] = $websiteNode->attr('href');
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
                $data[self::PHONE] = $dataNodes->eq(1)->text();
            }
        }

        return $data;
    }

    /**
     * @param string $html
     * @return int
     * @throws InvalidArgumentException
     */
    public function getMaxPageNumber(string $html): int
    {
        $crawler = new Crawler($html);
        $pageInputNode = $crawler->filter('input[name="Page"]');
        $pageNode = $pageInputNode->nextAll();

        $pageNumbers = $pageNode->text();
        [, $endPage] = preg_split('/\D+/', $pageNumbers, 2, PREG_SPLIT_NO_EMPTY);

        return (int)$endPage;
    }

    private function getCollegeInfo(Crawler $node): array
    {
        if ($node->count() < 0)
        {
            return [];
        }

        $data[self::COLLEGE_PAGE_URL] = $node->filter('a')->attr('href');
        $infoNode = $node->filter('h2');
        $data[self::NAME] = $infoNode->text();

        $locationNode = $infoNode->nextAll()->filter('.location');
        if ($locationNode->count() > 0)
        {
            [$city, $state] = explode(',', $locationNode->text());

            $data[self::CITY] = $city;
            $data[self::STATE] = trim($state);
        }
        $image = $node->selectImage($data[self::NAME])->filter('.school-image-large, .school-image');
        if ($image->count() > 0 )
        {
            $data[self::IMAGE_URL] = $image->attr('src');
        }

        return $data;
    }
}