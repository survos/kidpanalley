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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ChordPro\Song;

#[AsCommand('app:fetch-cho-files', 'Fetch .cho files from pathawks/Christmas-Songs GitHub repository')]
class FetchChoFilesCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient
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
            $code = pathinfo($relativePath, PATHINFO_FILENAME);
            
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
            
            // Parse ChordPro and store both structured data and basic lyrics array
            try {
                $parser = new \ChordPro\Parser();
                $song = $parser->parse($choContent);
                
                // Store ChordPro structured data
                $chordProData = [
                    'meta' => [],
                    'lines' => []
                ];
                
                foreach ($song as $line) {
                    if ($line instanceof \ChordPro\Metadata) {
                        $chordProData['meta'][$line->getName()] = $line->getValue();
                    } elseif ($line instanceof \ChordPro\Lyrics) {
                        $lineData = [];
                        foreach ($line->getBlocks() as $block) {
                            $lineData[] = [
                                'type' => $block->getChord() !== null ? 'chord' : 'text',
                                'text' => $block->getText(),
                                'chord' => $block->getChord()
                            ];
                        }
                        $chordProData['lines'][] = $lineData;
                    }
                }
                
                $lyrics->chordProData = $chordProData;
                
                // Also store basic lyrics array for backward compatibility
                $lyricsArray = $this->parseChoFile($choContent);
                $lyrics->lyrics = $lyricsArray;
                
            } catch (\Exception $e) {
                // Fallback to basic parsing if ChordPro parser fails
                $lyricsArray = $this->parseChoFile($choContent);
                $lyrics->lyrics = $lyricsArray;
            }
            
            $this->entityManager->persist($lyrics);
            $processedCount++;
            
            $io->text("Processed: $code");
        }

        $zip->close();
        $this->entityManager->flush();
        
        $io->success("Successfully processed $processedCount new .cho files from zip archive");

        return Command::SUCCESS;
    }

    private function parseChoFile(string $content): array
    {
        try {
            // Use the real ChordPro parser
            $parser = new \ChordPro\Parser();
            $song = $parser->parse($content);
            
            $lyrics = [];
            
            // Extract lyrics from each line, removing chords
            foreach ($song as $line) {
                $lineText = '';
                
                if ($line instanceof \ChordPro\Lyrics) {
                    foreach ($line->getBlocks() as $block) {
                        if ($block->getText() !== null) {
                            $lineText .= $block->getText();
                        }
                        // Ignore chord parts, we only want lyrics
                    }
                }
                
                $lineText = trim($lineText);
                if (!empty($lineText)) {
                    $lyrics[] = $lineText;
                }
            }
            
            return $lyrics;
            
        } catch (\Exception $e) {
            // Fallback to basic parsing if ChordPro parser fails
            $lines = explode("\n", $content);
            $lyrics = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    // Remove chord notation [C], [Am], etc.
                    $line = preg_replace('/\[[^\]]*\]/', '', $line);
                    // Remove directives {title:}, {key:}, etc.
                    $line = preg_replace('/\{[^}]*\}/', '', $line);
                    $line = trim($line);
                    if (!empty($line)) {
                        $lyrics[] = $line;
                    }
                }
            }
            
            return $lyrics;
        }
    }
}