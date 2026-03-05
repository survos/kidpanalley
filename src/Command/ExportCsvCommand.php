<?php

namespace App\Command;

use App\Entity\Audio;
use App\Entity\FileAsset;
use App\Entity\Song;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Export songs and their associated files as a flat CSV.
 *
 * Row layout (all rows share the same columns):
 *
 *   type  song             <song fields...>   <file fields...>
 *   song  "Best Friends"   writers=..., ...   (blank)
 *   file  "Best Friends"   (blank)            filename=..., school=..., ...
 *   song  "Hello World"    ...
 *   file  "Hello World"    ...
 *   file  "Hello World"    ...   ← second audio file for the same song
 *
 * Yes, this is intentionally flat and redundant. The recipients want it this way.
 */
#[AsCommand('app:export-csv', 'Export songs and file assets as a flat CSV')]
class ExportCsvCommand
{
    // Song-level columns (blank on file rows).
    // Note: 'title' is omitted — it already appears as the shared 'song' column.
    private const SONG_COLS = [
        'school',
        'year',
        'writers',
        'publisher',
        'featuredArtist',
        'musicians',
        'recordingCredits',
        'lyricsLength',
        'notes',
        'wordpressPageId',
    ];

    // File-level columns (blank on song rows)
    private const FILE_COLS = [
        'filename',
        'extension',
        'fileType',
        'school',
        'year',
        'size',
        'duration',
        'mimeType',
        'relativePath',
        'variant',
        'format',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('output CSV file path')]
        string $output = 'var/export/songs.csv',
        #[Option('only export songs that have at least one audio file')]
        bool $audioOnly = false,
        #[Option('filter by school name substring (case-insensitive)')]
        ?string $school = null,
        #[Option('filter by year')]
        ?int $year = null,
        #[Option('include song lyrics text in the export')]
        bool $withLyrics = false,
    ): int {
        $outputPath = str_starts_with($output, '/')
            ? $output
            : $this->projectDir . '/' . ltrim($output, '/');

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $io->error(sprintf('Cannot create output directory: %s', $dir));
            return Command::FAILURE;
        }

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $io->error(sprintf('Cannot open for writing: %s', $outputPath));
            return Command::FAILURE;
        }

        // Build header row — prefix song/file columns to avoid ambiguity in spreadsheets
        $songCols = self::SONG_COLS;
        if ($withLyrics) {
            $songCols[] = 'lyrics';
        }
        $fileCols = self::FILE_COLS;

        $header = array_merge(
            ['type', 'song'],
            array_map(static fn(string $c): string => 'song_' . $c, $songCols),
            array_map(static fn(string $c): string => 'file_' . $c, $fileCols),
        );
        fputcsv($handle, $header);

        // Query songs with their audio relations eager-loaded
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s', 'a', 'fa')
            ->from(Song::class, 's')
            ->leftJoin('s.audios', 'a')
            ->leftJoin('a.fileAsset', 'fa')
            ->orderBy('s.title', 'ASC');

        if ($audioOnly) {
            $qb->andWhere('a.id IS NOT NULL');
        }
        if ($school !== null) {
            $qb->andWhere('LOWER(fa.school) LIKE :school OR LOWER(s.school) LIKE :school')
               ->setParameter('school', '%' . mb_strtolower($school) . '%');
        }
        if ($year !== null) {
            $qb->andWhere('fa.year = :year OR s.year = :year')
               ->setParameter('year', $year);
        }

        /** @var Song[] $songs */
        $songs = $qb->getQuery()->getResult();

        $songCount = 0;
        $fileCount = 0;

        $progress = new ProgressBar($io, count($songs));
        $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        $progress->setMessage('');
        $progress->start();

        foreach ($songs as $song) {
            $progress->setMessage((string) $song->title);
            $progress->advance();

            // Skip junk titles: null, empty, macOS Office lock-file prefix (~$)
            $title = trim((string) $song->title);
            if ($title === '' || str_starts_with($title, '~$') || str_starts_with($title, '.')) {
                continue;
            }

            // --- song row ---
            $songRow = array_merge(
                ['song', $title],
                $this->songValues($song, $songCols),
                array_fill(0, count($fileCols), ''),  // file cols blank
            );
            fputcsv($handle, $songRow);
            $songCount++;

            // --- one file row per Audio record ---
            foreach ($song->audios as $audio) {
                $fa = $audio->fileAsset;
                $fileRow = array_merge(
                    ['file', $title],
                    array_fill(0, count($songCols), ''),  // song cols blank
                    $this->fileValues($audio, $fa, $fileCols),
                );
                fputcsv($handle, $fileRow);
                $fileCount++;
            }
        }

        fclose($handle);
        $progress->finish();
        $io->newLine(2);
        $io->success(sprintf(
            'Wrote %d song rows + %d file rows → %s',
            $songCount,
            $fileCount,
            $outputPath,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $cols
     * @return string[]
     */
    private function songValues(Song $song, array $cols): array
    {
        return array_map(function (string $col) use ($song): string {
            return match ($col) {
                'title'            => (string) $song->title,
                'school'           => (string) $song->school,
                'year'             => $song->year !== null ? (string) $song->year : '',
                'writers'          => (string) $song->writers,
                'publisher'        => (string) $song->publisher,
                'featuredArtist'   => (string) $song->featuredArtist,
                'musicians'        => (string) $song->musicians,
                'recordingCredits' => (string) $song->recordingCredits,
                'lyricsLength'     => $song->lyricsLength !== null ? (string) $song->lyricsLength : '',
                'notes'            => (string) $song->notes,
                'wordpressPageId'  => $song->wordpressPageId !== null ? (string) $song->wordpressPageId : '',
                'lyrics'           => (string) $song->lyrics,
                default            => '',
            };
        }, $cols);
    }

    /**
     * @param string[] $cols
     * @return string[]
     */
    private function fileValues(Audio $audio, FileAsset $fa, array $cols): array
    {
        return array_map(function (string $col) use ($audio, $fa): string {
            return match ($col) {
                'filename'     => $fa->filename,
                'extension'    => $fa->extension,
                'fileType'     => $fa->type,
                'school'       => (string) $fa->school,
                'year'         => $fa->year !== null ? (string) $fa->year : '',
                'size'         => $fa->size !== null ? (string) $fa->size : '',
                'duration'     => $fa->duration !== null ? number_format($fa->duration, 2) : '',
                'mimeType'     => (string) $fa->mimeType,
                'relativePath' => $fa->relativePath,
                'variant'      => (string) $audio->variant,
                'format'       => $audio->format,
                default        => '',
            };
        }, $cols);
    }
}
