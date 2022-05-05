<?php

namespace App\Command;

use App\Entity\College;
use App\Repository\CollegeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:get-all-colleges',
    description: 'Get data on all colleges',
)]
class GetAllCollegesDataCommand extends Command
{
    private const HTTPS = 'https';
    private const DOMAIN = 'www.princetonreview.com';
    private const COLLEGES_URL = '/college-search?ceid=cp-1022984';
    private const START_PAGE_NUMBER = 1;
    private const SUCCESS_STATUS_CODE = 200;

    private const NAME = 'name';
    private const CITY = 'city';
    private const STATE = 'state';
    private const ADDRESS = 'address';
    private const PHONE = 'phone';
    private const SITE = 'site';
    private const IMAGE_URL = 'image_url';

    private EntityManagerInterface $entityManager;
    private CollegeRepository $collegesRepo;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->collegesRepo = $this->entityManager->getRepository(College::class);
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('startPage', InputArgument::OPTIONAL, 'The page from which data collection will begin')
            ->addOption('advanced', null, InputOption::VALUE_NONE, 'Do I visit every college for advanced data collection')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isNextPageExist = true;
        $newCollegesCount = 0;
        $updatedCollegesCount = 0;

        $isAdvancedCollection = $input->getOption('advanced');
        $page = (int)($input->getArgument('startPage') ?? self::START_PAGE_NUMBER);
        try
        {
            $this->collegesRepo->setAllCollegesIsDeprecated();

            while($isNextPageExist)
            {
                $pageUrl = self::HTTPS . '://' . self::DOMAIN . self::COLLEGES_URL . "&page=$page";
                $html = $this->getHtml($pageUrl);

                $crawler = new Crawler($html);

                $collegesRows = $crawler->filter('.row .vertical-padding')->filter('h2');
                if (count($collegesRows) <= 0)
                {
                    break;
                }

                $collegesData = [];
                foreach ($collegesRows as $index => $row)
                {
                    $node = new Crawler($row);
                    try
                    {
                        $collegesData[] =  $this->getCollegeData($crawler, $node, $isAdvancedCollection);
                    }
                    catch (Exception $e)
                    {
                        $this->logger->error("Page: $page, College: $index;\n" . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine());
                    }
                }

                [$newColleges, $updatedColleges] = $this->saveCollegeData($collegesData);
                $io->info('Page: ' . $pageUrl . "\n"
                    .'Added colleges: ' . $newColleges . "\n"
                    .'Updated colleges: ' . $updatedColleges);

                $newCollegesCount += $newColleges;
                $updatedCollegesCount += $updatedColleges;
                $isNextPageExist = $this->isNextPageExist($crawler);
                $page++;
            }

            $deletedCollegesCount = $this->collegesRepo->deleteDeprecatedColleges();

            $io->success('Total colleges added: ' . $newCollegesCount . "\n"
                . 'Total colleges updated: ' . $updatedCollegesCount . "\n"
                . 'Total colleges deleted: '. $deletedCollegesCount);
        }
        catch (ExceptionInterface $e)
        {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function isNextPageExist(Crawler $crawler): bool
    {
        $pagination =  $crawler->filter('ul.pagination');
        $next =  $pagination->children()->filterXPath('//*[*[contains(.,"Next")]]');
        return $next->count() > 0;
    }

    private function getCollegeData(Crawler $root, Crawler $node, bool $isAdvancedCollection): array
    {
        if ($node->count() < 0)
        {
            return [];
        }

        $info[self::NAME] = $node->text();
        $collegePageHref = $node->filter('a')->attr('href');

        $locationNode = $node->nextAll()->filter('.location');
        if ($locationNode->count() > 0)
        {
            [$city, $state] = explode(',', $locationNode->text());

            $info[self::CITY] = $city;
            $info[self::STATE] = trim($state);
        }

        $image = $root->selectImage($info[self::NAME])->filter('.school-image-large, .school-image');
        if ($image->count() > 0 )
        {
            $info[self::IMAGE_URL] = self::HTTPS . ':' . $image->attr('src');
        }

        $collegePageUrl = self::HTTPS . '://' . self::DOMAIN . $collegePageHref;

        if ($isAdvancedCollection)
        {
            $info = array_merge($info, $this->getDataFromCollegePage($collegePageUrl));
        }

        return $info;
    }

    #[ArrayShape([
        self::SITE => 'string',
        self::ADDRESS => "string",
        self::PHONE => 'string'
    ])]
    private function getDataFromCollegePage(string $collegePageUrl): array
    {
        $html = $this->getHtml($collegePageUrl);

        $crawler = new Crawler($html);
        $info = [];

        $addressNode = $crawler->filter('div[itemprop="address"] > span');
        $address = '';
        foreach ($addressNode as $node)
        {
            $span = new Crawler($node);
            $address .=  ' ' . $span->text();
        }
        $info[self::ADDRESS] = $address;

        $websiteNode = $crawler->filter('div[itemprop="address"] > a');
        if ($websiteNode->count() > 0)
        {
            $info[self::SITE] = $websiteNode->attr('href');
        }

        $schoolDataNode = $crawler->filter('.school-contacts');
        $contactNode = $schoolDataNode->children()->eq(0);
        $rowsNode = $contactNode->children()->filter('.row');

        foreach ($rowsNode as $row)
        {
            $crawler = new Crawler($row);
            $dataNodes = $crawler->children();

            /*if ($dataNodes->eq(0)->text() === 'Address')
            {
                $info[self::ADDRESS] = $dataNodes->eq(1)->text();
            }*/

            if ($dataNodes->eq(0)->text() === 'Phone')
            {
                $info[self::PHONE] = $dataNodes->eq(1)->text();
            }
        }

        return $info;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function getHtml(string $url): string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() !== self::SUCCESS_STATUS_CODE)
        {
            throw new RuntimeException('Invalid server response. Status code: ' . $response->getStatusCode());
        }

        return $response->getContent();
    }

    /**
     * @param array $data
     * @return array [int, int]
     */
    private function saveCollegeData(array $data): array
    {
        $newCollegesCount = 0;
        $updatedCollegesCount = 0;

        foreach ($data as $record)
        {
            if (!isset($record[self::NAME]))
            {
                continue;
            }
            $name = mb_substr($record[self::NAME], 0, 255);

            $college = $this->collegesRepo->findOneBy([self::NAME => $name]);
            if (!$college)
            {
                $college = new College($name);
                $newCollegesCount++;
            }
            else
            {
                $updatedCollegesCount++;
            }

            if (isset($record[self::CITY]) && strlen($record[self::CITY]) <= 255)
            {
                $college->setCity($record[self::CITY]);
            }

            if (isset($record[self::STATE]) && strlen($record[self::STATE]) <= 50)
            {
                $college->setState($record[self::STATE]);
            }

            if (isset($record[self::SITE]) && strlen($record[self::SITE]) <= 255)
            {
                $college->setSite($record[self::SITE]);
            }

            if (isset($record[self::ADDRESS]) && strlen($record[self::ADDRESS]) <= 255)
            {
                $college->setAddress($record[self::ADDRESS]);
            }

            if (isset($record[self::PHONE])  && strlen($record[self::PHONE]) <= 50)
            {
                $college->setPhone($record[self::PHONE]);
            }

            if (isset($record[self::IMAGE_URL])  && strlen($record[self::IMAGE_URL]) <= 255)
            {
                $college->setImageUrl($record[self::IMAGE_URL]);
            }

            $college->setIsDeprecated(false);

            $this->entityManager->persist($college);
        }
        $this->entityManager->flush();

        return [$newCollegesCount, $updatedCollegesCount];
    }
}
