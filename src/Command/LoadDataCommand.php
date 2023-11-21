<?php

namespace App\Command;

use App\Message\FetchYoutubeChannelMessage;
use App\Message\LoadSongsMessage;
use App\Repository\SongRepository;
use App\Services\AppService;
use App\Services\DocxConversion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[\Symfony\Component\Console\Attribute\AsCommand('app:load-data', "Load the songs and videos")]
class LoadDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ParameterBagInterface $bag,
                                private MessageBusInterface $bus,
                                private readonly AppService $appService,
                                private SongRepository $songRepository,

                                string $name = null)
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

    private function loadLyricFiles()
    {

        $dir = $this->projectDir . '/../data/kpa/Lyrics individual songs';
        $this->appService->loadLyrics($dir);

        // get the collections with sqlite files
        $finder = new Finder();
        $collectionIds = [];

        return;


        foreach ($finder->in($dir)->name('*.doc') as $file) {
            $title = $file->getFilenameWithoutExtension();
            if ($song = $this->songRepository->findOneBy(['title' => $title])) {
                // yay!
                $process = new Process(['catdoc', $file->getRealPath()]);
                $process->run();
// executes after the command finishes
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                $text = $process->getOutput();
                $song->setLyrics($text);
                dd($song);
            } else {
                continue;
            }



            dd($filename, $text, $file->getRealPath());
            $text = $converter->convertToText();
            dd($text, $file);
            if (preg_match('/coll-(\d*)/', $file->getFilename(), $m)) {
                $collectionIds[] = $m[1];
            }
        }
        if (empty($collectionIds)) {
            $io->error("No collections in " . $collectionSqliteDir);
            return self::FAILURE;
        }

        $this->entityManager->flush();

    }
}
