<?php

namespace App\Command;

use App\Entity\College;
use App\Repository\CollegeRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    private const LINK = 'https://www.princetonreview.com/college-search?ceid=cp-1022984';
    private const START_PAGE = 1;
    private const HTTPS = 'https';
    private const SUCCESS_STATUS_CODE = 200;

    private EntityManagerInterface $entityManager;
    private CollegeRepository $collegesRepo;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->collegesRepo = $this->entityManager->getRepository(College::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try
        {
            $this->collegesRepo->setAllCollegesIsDeprecated();

            $isNextPageExist = true;
            $newCollegesCount = 0;
            $updatedCollegesCount = 0;

            $pageNumber = self::START_PAGE;
            while($isNextPageExist)
            {
                $pageUrl = self::LINK . "&page=$pageNumber";
                $html = $this->getHtml($pageUrl);

                $crawler = new Crawler($html);

                $colleges = $crawler->filter('h2')->each(function (Crawler $node) use ($crawler) {
                    return $this->getCollegeData($crawler, $node);
                });

                [$newColleges, $updatedColleges] = $this->saveCollegeData($colleges);
                $io->info('Page: ' . $pageUrl . "\n"
                    .'Added colleges: ' . $newColleges . "\n"
                    .'Updated colleges: ' . $updatedColleges);

                $newCollegesCount += $newColleges;
                $updatedCollegesCount += $updatedColleges;
                $isNextPageExist = $this->isNextPageExist($crawler);
                $pageNumber++;
            }

            $deletedColleges = $this->collegesRepo->deleteDeprecatedColleges();

            $io->success('Total colleges added: ' . $newCollegesCount . "\n"
                . 'Total colleges updated: ' . $updatedCollegesCount . "\n"
                . 'Total colleges deleted: '. $deletedColleges);
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

    private function getCollegeData(Crawler $root,Crawler $node): array
    {
        if ($node->count() < 0)
        {
            return [];
        }

        $info['name'] = $node->text();

        $locationNode = $node->nextAll()->filter('.location');
        if ($locationNode->count() > 0)
        {
            [$city, $state] = explode(',', $locationNode->text());

            $info['city'] = $city;
            $info['state'] = trim($state);
        }

        $image = $root->selectImage($info['name'])->filter('.school-image-large, .school-image');
        if ($image->count() > 0 )
        {
            $info['image_url'] = self::HTTPS . ':' . $image->attr('src');
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
            if (!isset($record['name']))
            {
                continue;
            }

            $college = $this->collegesRepo->findOneBy(['name' => $record['name']]);
            if (!$college)
            {
                $college = new College($record['name']);
                $newCollegesCount++;
            }
            else
            {
                $updatedCollegesCount++;
            }

            if (isset($record['city']))
            {
                $college->setCity($record['city']);
            }

            if (isset($record['state']))
            {
                $college->setState($record['state']);
            }

            if (isset($record['image_url']))
            {
                $college->setImageUrl($record['image_url']);
            }

            $college->setIsDeprecated(false);

            $this->entityManager->persist($college);
        }
        $this->entityManager->flush();

        return [$newCollegesCount, $updatedCollegesCount];
    }
}
