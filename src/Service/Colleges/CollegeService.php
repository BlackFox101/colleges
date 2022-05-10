<?php
declare(strict_types=1);

namespace App\Service\Colleges;

use App\Entity\College;
use App\Repository\CollegeRepository;
use App\Service\Http\HttpRequest;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class CollegeService
{
    public const HTTPS = 'https';
    public const DOMAIN = 'www.princetonreview.com';
    public const COLLEGES_URL = '/college-search?ceid=cp-1022984';

    public const NAME = 'name';
    public const CITY = 'city';
    public const STATE = 'state';
    public const ADDRESS = 'address';
    public const PHONE = 'phone';
    public const SITE = 'site';
    public const IMAGE_URL = 'image_url';

    private CollegeRepository $collegeRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, CollegeRepository $collegeRepository, LoggerInterface $logger)
    {
        $this->collegeRepository = $collegeRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function getCollegesData(int $startPage, SymfonyStyle $io): array
    {
        $parser = new CollegesParser($this->logger);

        $newCollegesCount = 0;
        $updatedCollegesCount = 0;
        for ($page = $startPage; $parser->isNextPageExist(); $page++)
        {
            $pageUrl = self::HTTPS . '://' . self::DOMAIN . self::COLLEGES_URL . "&page=$page";

            try
            {
                $html = HttpRequest::getHtml($pageUrl);
            }
            catch (ExceptionInterface $e)
            {
                $this->logger->error("Error when getting the colleges list page.\n"
                    . 'Page: ' . $pageUrl
                    . $e->getMessage() . "\n"
                    . $e->getFile() . ':' . $e->getLine());
                break;
            }

            $colleges = $parser->getDataFromCollegesList($page, $html);
            [$newColleges, $updatedColleges] = $this->saveColleges($colleges);

            $newCollegesCount += $newColleges;
            $updatedCollegesCount += $updatedColleges;

            $io->info('Page: ' . $pageUrl . "\n"
                .'Found colleges: ' . count($colleges) . "\n");
        }
        $this->entityManager->flush();

        return [$newCollegesCount, $updatedCollegesCount];
    }

    private function saveColleges(array $collegesData): array
    {
        $newCollegesCount = 0;
        $updatedCollegesCount = 0;

        foreach ($collegesData as $record)
        {
            if (!isset($record[self::NAME]))
            {
                continue;
            }
            $name = mb_substr($record[self::NAME], 0, 255);

            $college = $this->collegeRepository->findOneBy([self::NAME => $name]);
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

        return [$newCollegesCount, $updatedCollegesCount];
    }
}