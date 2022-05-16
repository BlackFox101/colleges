<?php
declare(strict_types=1);

namespace App\Service\Colleges\Handlers;

use App\Entity\College;
use App\Repository\CollegeRepository;
use App\Service\Http\HttpRequest;

class CollegeDataHandler
{
    private const NAME = 'name';
    private const CITY = 'city';
    private const STATE = 'state';
    private const ADDRESS = 'address';
    private const PHONE = 'phone';
    private const SITE = 'site';
    private const IMAGE_URL = 'image_url';
    private const COLLEGE_PAGE_URL = 'college_page_url';

    private CollegeRepository $collegeRepository;

    public function __construct(CollegeRepository $collegeRepository)
    {
        $this->collegeRepository = $collegeRepository;
    }

    /**
     * @param array $collegesData
     * @param string $siteDomain
     * @return array [newColleges[], updatedColleges[]]
     */
    public function handleCollegesBySurfaceData(array $collegesData, string $siteDomain): array
    {
        $siteUrl = HttpRequest::HTTPS . '://' . $siteDomain;

        $newColleges = [];
        $updatedColleges = [];
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
                $newColleges[] = $college;
            }
            else
            {
                $updatedColleges[] = $college;
            }

            if (isset($record[self::CITY]) && strlen($record[self::CITY]) <= 255)
            {
                $college->setCity($record[self::CITY]);
            }

            if (isset($record[self::STATE]) && strlen($record[self::STATE]) <= 50)
            {
                $college->setState($record[self::STATE]);
            }

            if (isset($record[self::IMAGE_URL]) && strlen($record[self::IMAGE_URL]) <= 255)
            {
                $college->setImageUrl(HttpRequest::HTTPS . $record[self::IMAGE_URL]);
            }

            if (isset($record[self::COLLEGE_PAGE_URL]) && strlen($record[self::COLLEGE_PAGE_URL]) <= 255)
            {
                $college->setCollegePageUrl($siteUrl . $record[self::COLLEGE_PAGE_URL]);
            }

            $college->setIsDeprecated(false);
        }

        return [$newColleges, $updatedColleges];
    }

    /**
     * @param College $college
     * @param array $data
     * @return void
     */
    public function addDetailedInfoToCollege(College $college, array $data): void
    {
        if (isset($data[self::SITE]) && strlen($data[self::SITE]) <= 255)
        {
            $college->setSite($data[self::SITE]);
        }

        if (isset($data[self::ADDRESS]) && strlen($data[self::ADDRESS]) <= 255)
        {
            $college->setAddress($data[self::ADDRESS]);
        }

        if (isset($data[self::PHONE]) && strlen($data[self::PHONE]) <= 50)
        {
            $college->setPhone($data[self::PHONE]);
        }
    }
}