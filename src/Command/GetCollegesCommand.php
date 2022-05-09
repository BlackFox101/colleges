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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:get-colleges',
    description: 'Get data on all colleges',
)]
class GetCollegesCommand extends Command
{
    private const START_PAGE_NUMBER = 1;

    private CollegeRepository $collegeRepository;
    private CollegeService $collegeService;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        parent::__construct();
        $this->collegeRepository = $entityManager->getRepository(College::class);
        $this->collegeService = new CollegeService($entityManager, $this->collegeRepository, $logger);
    }

    protected function configure(): void
    {
        $this->addArgument('startPage', InputArgument::OPTIONAL, 'The page from which data collection will begin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $startPage = (int)($input->getArgument('startPage') ?? self::START_PAGE_NUMBER);
        try
        {
            // Сделать все колледжи устаревшими
            $this->collegeRepository->setAllCollegesIsDeprecated();
            // Получить коллежди
            [$newCollegesCount, $updatedCollegesCount] = $this->collegeService->getCollegesData($startPage, $io);
            // Удалить старые колледжи
            $deletedCollegesCount = $this->collegeRepository->deleteDeprecatedColleges();

            $io->success('Total colleges added: ' . $newCollegesCount . "\n"
                . 'Total colleges updated: ' . $updatedCollegesCount . "\n"
                . 'Total colleges deleted: '. $deletedCollegesCount);
        }
        catch (Exception $e )
        {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
