<?php

namespace App\Command;

use App\Entity\College;
use App\Repository\CollegeRepository;
use App\Service\Colleges\CollegeService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:get-colleges',
    description: 'Add a short description for your command. 
    The option specifies how to collect the data(--option=surface):
        `surface` - collecting a list of colleges
        `detailed` - collecting a list of colleges with additional information
        `new` - collecting additional information only for new colleges
    If you specify pages, the team will not delete colleges that were not found, 
    but if you specify the start from page 1 and do not specify the last one, 
    the deletion will occur.',
)]
class GetCollegesCommand extends Command
{
    private const OPTION_SURFACE_DATA = 'surface';
    private const OPTION_DETAILED_INFO = 'detailed';
    private const OPTION_DETAILED_IF_NEW = 'new';

    private CollegeService $collegeService;
    private CollegeRepository $collegeRepository;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        parent::__construct();
        $this->collegeService = new CollegeService($entityManager->getRepository(College::class), $logger);
        $this->collegeRepository = $entityManager->getRepository(College::class);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('startPage', InputArgument::OPTIONAL, 'Start page')
            ->addArgument('quantity', InputArgument::OPTIONAL, 'Pages quantity')
            ->addOption('option', null, InputOption::VALUE_REQUIRED, 'Specifies a college data collection option', 'surface')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $startPage = $input->getArgument('startPage');
        $quantity = $input->getArgument('quantity');
        $option = $this->getOption($input->getOption('option'));

        $isDeleteOldNeeded = $this->isDeleteNeeded($startPage, $quantity);
        try
        {
            if ($isDeleteOldNeeded)
            {
                // Сделать все колледжи устаревшими
                $this->collegeRepository->setAllCollegesIsDeprecated();
            }

            // Получить коллежди
            [$newColleges, $updatedColleges] = $this->collegeService->getColleges($startPage, $quantity);

            // Получить подробную информацию у новых колледжей
            if ($option === self::OPTION_DETAILED_IF_NEW)
            {
                $this->collegeService->getDetailedCollegesInfo($newColleges);
            }

            $colleges = array_merge($newColleges, $updatedColleges);
            // Получить подробную информацию у всех колледжей
            if ($option === self::OPTION_DETAILED_INFO)
            {
                $this->collegeService->getDetailedCollegesInfo($colleges);
            }

            // Сохранить коллежди
            $this->collegeRepository->saveColleges($colleges);

            if ($isDeleteOldNeeded)
            {
                // Удалить старые колледжи
                $deletedCollegesCount = $this->collegeRepository->deleteDeprecatedColleges();
            }

            $outputText = 'Total colleges added: ' . count($newColleges) . "\n"
                . 'Total colleges updated: ' . count($updatedColleges);
            if ($isDeleteOldNeeded)
            {
                $outputText .=  "\n" . 'Total colleges deleted: '. $deletedCollegesCount;
            }

            $io->success($outputText);
        }
        catch (HttpExceptionInterface|TransportExceptionInterface|Exception $e)
        {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function isDeleteNeeded(?int $startPage, ?int $quantity): bool
    {
        if (!isset($startPage) && !isset($quantity))
        {
            return true;
        }

        if (isset($startPage, $quantity))
        {
            return false;
        }

        if (!isset($quantity))
        {
            return $startPage === 1;
        }

        return false;
    }

    private function getOption(?string $option): string
    {
        return match ($option) {
            'detailed' => self::OPTION_DETAILED_INFO,
            'new' => self::OPTION_DETAILED_IF_NEW,
            default => self::OPTION_SURFACE_DATA,
        };
    }
}
