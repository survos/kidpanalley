<?php

namespace App\Command;

use App\Entity\Lyrics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ChordPro\Song;
use function Symfony\Component\String\u;

#[AsCommand('app:fetch-cho-files', 'fetch .cho files from the christmas-songs repo')]
class FetchChoFilesCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private SluggerInterface $asciiSlugger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $zipUrl = 'https://github.com/pathawks/Christmas-Songs/archive/refs/heads/master.zip';
        $zipFile = $this->projectDir . '/var/christmas-songs.zip';
        $extractDir = $this->projectDir . '/var/christmas-songs';

        // Create var directory if it doesn't exist
        $this->filesystem->mkdir(dirname($zipFile));

        // Download zip file if it doesn't exist
        if (!$this->filesystem->exists($zipFile)) {
            $io->info('Downloading Christmas Songs zip file...');

            try {
                $response = $this->httpClient->request('GET', $zipUrl);

                if ($response->getStatusCode() !== 200) {
                    $io->error('Failed to download zip file: HTTP ' . $response->getStatusCode());
                    return Command::FAILURE;
                }

                // Write the content to file only after successful download
                file_put_contents($zipFile, $response->getContent());
                $io->success('Zip file downloaded successfully.');

            } catch (\Exception $e) {
                $io->error('Failed to download zip file: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $io->info('Zip file already exists, skipping download.');
        }

        // Read directly from zip file
        $io->info('Reading .cho files directly from zip archive...');

        $choFiles = [];
        $zip = new \ZipArchive();
        $zipStatus = $zip->open($zipFile);

        if ($zipStatus !== true) {
            $io->error('Failed to open zip file: ' . $zipStatus);
            return Command::FAILURE;
        }

        // Find all .cho files in the zip
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'cho') {
                $choFiles[] = [
                    'filename' => $filename,
                    'index' => $i
                ];
            }
        }

        if (empty($choFiles)) {
            $io->warning('No .cho files found in the repository.');
            return Command::SUCCESS;
        }

        $io->success('Found ' . count($choFiles) . ' .cho files');

        // Process .cho files and populate Lyrics entity
        $lyricsRepository = $this->entityManager->getRepository(Lyrics::class);
        $processedCount = 0;

        foreach ($choFiles as $fileInfo) {
            $relativePath = $fileInfo['filename'];
            $code = $this->asciiSlugger->slug($relativePath)->toString();

            // Check if lyrics already exist
            $existingLyrics = $lyricsRepository->findOneBy(['code' => $code]);

            if ($existingLyrics) {
                $io->text("Skipping existing lyrics: $code");
                continue;
            }

            // Read .cho file content directly from zip
            $choContent = $zip->getFromIndex($fileInfo['index']);

            if ($choContent === false) {
                $io->error("Failed to read file from zip: {$relativePath}");
                continue;
            }

            // Create new Lyrics entity
            $lyrics = new Lyrics();
            $lyrics->code = $code;
            $lyrics->file = $relativePath;
            $lyrics->text = $choContent;

            $this->entityManager->persist($lyrics);
            $processedCount++;

            $io->text("Processed: $code");
        }

        $zip->close();
        $this->entityManager->flush();

        $io->success("Successfully processed $processedCount new .cho files from zip archive");

        return Command::SUCCESS;
    }
}
