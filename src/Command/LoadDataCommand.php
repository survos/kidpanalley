<?php

namespace App\Command;

use App\Message\FetchYoutubeChannelMessage;
use App\Message\LoadSongsMessage;
use App\Repository\SongRepository;
use App\Services\AppService;
use App\Services\DocxConversion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsCommand('app:load', "Load the songs and videos")]
class LoadDataCommand
{
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ParameterBagInterface $bag,
                                private MessageBusInterface $bus,
                                private readonly AppService $appService,
                                private SongRepository $songRepository,
    #[Autowire('%kernel.project_dir%')] private string $projectDir,
    )
    {
    }
    public function __invoke(
        SymfonyStyle $io,
        #[Option] bool $video = true,
        #[Option] bool $songs = true,
    ): int
    {

        if ($video) {
            $this->bus->dispatch(new FetchYoutubeChannelMessage(), [
                new DeduplicateStamp('video-lock'),
            ]);
            $io->success('Videos Load Requested');
        }

        if ($songs) {
            $this->bus->dispatch(new LoadSongsMessage());
            $io->success('Songs Load Requested');
        }
        return Command::SUCCESS;
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
                $text = str_replace("\n\n", "\n", $text);
                $song->setLyrics($text);
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
