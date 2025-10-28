<?php

namespace App\Services;

use App\Entity\Song;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LyricsImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private SongRepository $songRepository,
        private DocumentTextExtractor $textExtractor,
        private LoggerInterface $logger,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Import lyrics from all Word documents in a directory
     *
     * @param string $directory Path to directory containing Word files
     * @param bool $dryRun If true, don't persist changes
     * @param bool $skipExisting If true, skip songs that already have lyrics
     * @param SymfonyStyle|null $io For console output
     * @return array Statistics about the import
     */
    public function importFromDirectory(
        string $directory,
        bool $dryRun = false,
        bool $skipExisting = false,
        ?SymfonyStyle $io = null
    ): array {
        $stats = [
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!file_exists($directory)) {
            $this->logger->warning("Directory does not exist: $directory");
            throw new \InvalidArgumentException("Directory does not exist: $directory");
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.doc*')->sortByName();

        foreach ($finder as $file) {
            $stats['processed']++;
            $absoluteFilePath = $file->getRealPath();
            $filename = $file->getFilename();

            if ($io) {
                $io->text(sprintf('Processing: %s', $filename));
            }

            try {
                $this->importFromFile(
                    $absoluteFilePath,
                    $file->getExtension(),
                    $file->getFilenameWithoutExtension(),
                    $skipExisting,
                    $stats
                );
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->logger->error('Error processing file', [
                    'file' => $absoluteFilePath,
                    'error' => $e->getMessage(),
                ]);

                if ($io) {
                    $io->warning(sprintf('  Error: %s', $e->getMessage()));
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
            $this->logger->info('Lyrics import completed', $stats);
        } else {
            $this->em->clear();
            $this->logger->info('Lyrics import completed (dry run)', $stats);
        }

        return $stats;
    }

    /**
     * Import lyrics from a single file
     */
    private function importFromFile(
        string $filePath,
        string $extension,
        string $filenameWithoutExtension,
        bool $skipExisting,
        array &$stats
    ): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("File is not readable: $filePath");
        }

        // Extract text based on file type
        if ($extension === 'doc') {
            $this->importFromDocFile($filePath, $filenameWithoutExtension, $skipExisting, $stats);
        } else {
            $this->importFromDocxFile($filePath, $filenameWithoutExtension, $skipExisting, $stats);
        }
    }

    /**
     * Import from legacy .doc file using catdoc
     */
    private function importFromDocFile(
        string $filePath,
        string $defaultTitle,
        bool $skipExisting,
        array &$stats
    ): void {
        $text = $this->textExtractor->extractFromDoc($filePath);
        $text = $this->normalizeLineBreaks($text);

        // Split on form feed (multiple songs per file)
        $songTexts = preg_split("|\f|", $text);

        foreach ($songTexts as $songText) {
            if (empty(trim($songText))) {
                continue;
            }

            $songData = $this->parseSongText($songText);

            if (empty($songData['title'])) {
                $this->logger->warning('Song without title', ['file' => $filePath]);
                continue;
            }

            $this->createOrUpdateSong($songData, $skipExisting, $stats);
        }
    }

    /**
     * Import from modern .docx file
     */
    private function importFromDocxFile(
        string $filePath,
        string $defaultTitle,
        bool $skipExisting,
        array &$stats
    ): void {
        $text = $this->textExtractor->extractFromDocx($filePath);
        $text = $this->normalizeLineBreaks($text);

        $songData = [
            'title' => $defaultTitle,
            'lyrics' => $text,
            'by' => null,
        ];

        $this->createOrUpdateSong($songData, $skipExisting, $stats);
    }

    /**
     * Parse song text into structured data
     */
    private function parseSongText(string $text): array
    {
        $lines = explode("\n", $text);

        // Remove blank lines
        $lines = array_filter($lines, fn($str) => !empty(trim($str)));
        $lines = array_values($lines); // Re-index

        if (empty($lines)) {
            return ['title' => null, 'lyrics' => '', 'by' => null];
        }

        // First line is usually the title
        $title = trim(array_shift($lines));

        // Second line might be "by [author]"
        $by = null;
        if (!empty($lines) && stripos($lines[0], 'by ') === 0) {
            $by = trim(array_shift($lines));
        }

        // Rest is lyrics
        $lyrics = implode("\n", $lines);

        return [
            'title' => $title,
            'lyrics' => $lyrics,
            'by' => $by,
        ];
    }

    /**
     * Create or update a song entity
     */
    private function createOrUpdateSong(array $songData, bool $skipExisting, array &$stats): void
    {
        $title = $songData['title'];
        $lyrics = $songData['lyrics'];

        // Find existing song
        $song = $this->songRepository->findOneBy(['title' => $title]);

        if ($song) {
            if ($skipExisting && !empty($song->getLyrics())) {
                $stats['skipped']++;
                return;
            }
        } else {
            // Create new song
            $code = Song::createCode($title);
            $song = new Song();
            $song->setCode($code);
            $song->setTitle($title);
            $this->em->persist($song);
        }

        $song->setLyrics($lyrics);

        // Validate
        $errors = $this->validator->validate($song);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \Exception('Validation failed: ' . implode(', ', $errorMessages));
        }

        $stats['imported']++;
    }

    /**
     * Normalize line breaks (remove double newlines)
     */
    private function normalizeLineBreaks(string $text): string
    {
        return str_replace("\n\n", "\n", $text);
    }
}
