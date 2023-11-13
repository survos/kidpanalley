<?php

namespace App\Command;

use App\Message\FetchYoutubeChannelMessage;
use App\Message\LoadSongsMessage;
use App\Services\AppService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[\Symfony\Component\Console\Attribute\AsCommand('app:load-data', "Load the songs and videos")]
class LoadDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ParameterBagInterface $bag,
                                private MessageBusInterface $bus,
                                private readonly AppService $appService, string $name = null)
    {
        parent::__construct($name);
    }
    protected function configure()
    {
        $this
//            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        $this->bus->dispatch(new LoadSongsMessage());
        $io->success('Songs Load Requested');

        $this->bus->dispatch(new FetchYoutubeChannelMessage());
        $io->success('Videos Load Requested');


        return self::SUCCESS;
    }
}
