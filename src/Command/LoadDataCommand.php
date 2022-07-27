<?php

namespace App\Command;

use App\Services\AppService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Symfony\Component\Console\Attribute\AsCommand('app:load-data')]
class LoadDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly AppService $appService, string $name = null)
    {
        parent::__construct($name);
    }
    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // @todo: get filename
        $this->appService->loadSongs();

        $io->success('Songs Loaded');

        return 0;
    }
}
