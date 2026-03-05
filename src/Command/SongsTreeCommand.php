<?php

namespace App\Command;

use App\Service\SongMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Survos\JsonlBundle\IO\JsonlReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:songs-tree', 'Group JSONL file assets by canonical song title and persist to Song records')]
class SongsTreeCommand
{
    private const SONG_TYPES  = ['audio', 'lyrics', 'chart'];
    private const AUDIO_EXTS  = ['mp3', 'm4a', 'wav', 'aif', 'aiff', 'flac', 'ogg', 'w64'];
    private const LYRICS_EXTS = ['doc', 'docx', 'txt', 'rtf', 'pages'];
    private const CHART_EXTS  = ['pdf', 'musx', 'mus', 'sib', 'mxl', 'musicxml', 'xml', 'song', 'plj'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly SongMatcher $songMatcher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('path to JSONL file')]
        string $jsonl = 'data/seagate.jsonl',
        #[Option('max rows to read')]
        ?int $limit = null,
        #[Option('minimum unique files per song to display (0 = all)')]
        int $min = 1,
        #[Option('show only audio files in tree')]
        bool $audioOnly = false,
        #[Option('show full relative path instead of filename')]
        bool $fullPath = false,
        #[Option('filter output by title substring (case-insensitive)')]
        ?string $filter = null,
        #[Option('output as JSON instead of tree')]
        bool $json = false,
        #[Option('persist grouped files onto Song records, creating Songs if missing')]
        bool $persist = false,
        #[Option('reset Song.files before repopulating (requires --persist)')]
        bool $reset = false,
    ): int {
        $path = $this->resolvePath($jsonl);
        if (!is_file($path)) {
            $io->error(sprintf('JSONL not found: %s', $path));
            return Command::FAILURE;
        }

        if ($io->isVerbose()) {
            $io->text(sprintf('Reading %s...', $path));
        }

        // ------------------------------------------------------------------
        // Scan pass: build song tree from JSONL
        // ------------------------------------------------------------------
        $songs   = [];
        $skipped = 0;
        $total   = 0;

        $scanProgress = new ProgressBar($io);
        $scanProgress->setFormat('%current% rows [%bar%] %message%');
        $scanProgress->setMessage('scanning');
        $scanProgress->start();

        $reader = JsonlReader::open($path);
        foreach ($reader as $row) {
            $total++;
            if ($limit !== null && $total > $limit) {
                break;
            }

            $filename     = (string)($row['filename'] ?? '');
            $rawPath      = (string)($row['path'] ?? '');
            $relativePath = (string)($row['relative_path'] ?? '');

            if ($filename === '' || $rawPath === '') {
                $skipped++;
                $scanProgress->advance();
                continue;
            }

            $rawExt = strtolower(trim((string)($row['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION))));
            $ext    = $this->normalizeExt($rawExt, $filename);
            $type   = $this->detectType($ext);

            if (!in_array($type, self::SONG_TYPES, true)) {
                $skipped++;
                $scanProgress->advance();
                continue;
            }

            if ($audioOnly && $type !== 'audio') {
                $skipped++;
                $scanProgress->advance();
                continue;
            }

            $title = $this->songMatcher->normalizeTitle($filename);

            if ($filter !== null && !str_contains(mb_strtolower($title), mb_strtolower($filter))) {
                $skipped++;
                $scanProgress->advance();
                continue;
            }

            [$school, $year] = $this->extractSchoolYear($relativePath);

            $songKey = mb_strtolower($title);
            if (!isset($songs[$songKey])) {
                $songs[$songKey] = ['canonical' => $title, 'school' => $school, 'year' => $year, 'files' => []];
            }

            // Deduplicate by stem+type — collapses same file across sync locations
            $stem    = mb_strtolower(pathinfo($filename, PATHINFO_FILENAME));
            $fileKey = $stem . '.' . $type;

            if (!isset($songs[$songKey]['files'][$fileKey])) {
                $songs[$songKey]['files'][$fileKey] = [
                    'filename' => $filename,
                    'ext'      => $ext,
                    'type'     => $type,
                    'contexts' => [],
                ];
            }

            $songs[$songKey]['files'][$fileKey]['contexts'][] = [
                'school'       => $school,
                'year'         => $year,
                'relativePath' => $relativePath,
            ];

            $scanProgress->setMessage($title);
            $scanProgress->advance();
        }

        $scanProgress->finish();
        $io->newLine();

        ksort($songs);

        if ($min > 1) {
            $songs = array_filter($songs, static fn(array $s): bool => count($s['files']) >= $min);
        }

        $songCount = count($songs);
        $fileCount = array_sum(array_map(static fn(array $s): int => count($s['files']), $songs));

        $io->success(sprintf('%d songs, %d unique files (%d rows read, %d skipped)', $songCount, $fileCount, $total, $skipped));

        // ------------------------------------------------------------------
        // Persist
        // ------------------------------------------------------------------
        if ($persist) {
            $this->persistToSongs($songs, $reset, $io);
        }

        // ------------------------------------------------------------------
        // JSON output
        // ------------------------------------------------------------------
        if ($json) {
            $io->writeln(json_encode(array_values($songs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        // ------------------------------------------------------------------
        // Tree output — only when explicitly requested via -v or --filter
        // ------------------------------------------------------------------
        if (!$io->isVerbose() && $filter === null && !$json) {
            $io->note('Use -v or --filter to see the tree. Use --persist to write to the database.');
            return Command::SUCCESS;
        }

        $typeIcons = ['audio' => '🎵', 'lyrics' => '📄', 'chart' => '🎼'];

        foreach ($songs as $song) {
            $io->writeln('<fg=bright-white>' . $song['canonical'] . '</>');

            $byType = [];
            foreach ($song['files'] as $file) {
                $byType[$file['type']][] = $file;
            }

            foreach (['audio', 'lyrics', 'chart'] as $t) {
                if (!isset($byType[$t])) {
                    continue;
                }
                foreach ($byType[$t] as $file) {
                    $icon = $typeIcons[$t] ?? '📁';

                    $contextLabels = [];
                    foreach ($file['contexts'] as $ctx) {
                        $parts = array_filter([$ctx['school'], $ctx['year'] ? (string)$ctx['year'] : null]);
                        if ($parts) {
                            $contextLabels[] = implode(' ', $parts);
                        }
                        if ($fullPath && isset($ctx['relativePath']) && $ctx['relativePath'] !== '') {
                            $contextLabels[] = $ctx['relativePath'];
                        }
                    }
                    $contextLabels = array_values(array_unique($contextLabels));

                    $ctxStr  = $contextLabels ? '  <fg=gray>' . implode(', ', $contextLabels) . '</>' : '';
                    $dupeStr = count($file['contexts']) > 1
                        ? sprintf('  <comment>(%dx)</comment>', count($file['contexts']))
                        : '';

                    $io->writeln(sprintf(
                        '   %s  <info>%s</info>  <fg=gray>[%s]</>%s%s',
                        $icon,
                        $file['filename'],
                        $file['ext'],
                        $dupeStr,
                        $ctxStr,
                    ));
                }
            }

            $io->writeln('');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{canonical:string, school:?string, year:?int, files:array<string,mixed>}> $songs
     */
    private function persistToSongs(array $songs, bool $reset, SymfonyStyle $io): void
    {
        $io->section('Persisting to Song records...');

        $batchSize = 100;
        $processed = 0;
        $created   = 0;
        $updated   = 0;

        // Warm the matcher cache once from existing DB records
        $this->songMatcher->warmCache();

        $progress = new ProgressBar($io, count($songs));
        $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        $progress->setMessage('');
        $progress->start();

        foreach ($songs as $songData) {
            $progress->setMessage($songData['canonical']);
            $progress->advance();

            // Derive school/year from the first context that has them (for code generation)
            $school = $songData['school'];
            $year   = $songData['year'];
            if (!$year) {
                foreach ($songData['files'] as $file) {
                    foreach ($file['contexts'] as $ctx) {
                        if ($ctx['year']) {
                            $year   = $ctx['year'];
                            $school = $ctx['school'] ?? $school;
                            break 2;
                        }
                    }
                }
            }

            $isNew = false;
            $song  = $this->songMatcher->findOrCreate(
                rawTitle: $songData['canonical'],
                school: $school,
                year: $year,
                persist: true,
            );

            if ($song->id === null) {
                $created++;
                $isNew = true;
            } else {
                $updated++;
            }

            $processed++;
            if ($processed % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->songMatcher->clearCache();
                $this->songMatcher->warmCache();
            }
        }

        $this->entityManager->flush();
        $progress->finish();
        $io->newLine(2);
        $io->success(sprintf('Created %d, updated %d Song records', $created, $updated));
    }

    private function detectType(string $ext): string
    {
        if (in_array($ext, self::AUDIO_EXTS, true)) {
            return 'audio';
        }
        if (in_array($ext, self::LYRICS_EXTS, true)) {
            return 'lyrics';
        }
        if (in_array($ext, self::CHART_EXTS, true)) {
            return 'chart';
        }
        return 'other';
    }

    private function normalizeExt(string $extension, string $filename = ''): string
    {
        $extension = trim(strtolower($extension));
        if ($extension === '' && $filename !== '') {
            $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        }
        $extension = (string)preg_replace('/\s.*$/', '', $extension);
        $extension = (string)preg_replace('/[^a-z0-9]/', '', $extension);
        return $extension === '' ? 'unknown' : mb_substr($extension, 0, 16);
    }

    private function extractSchoolYear(string $relativePath): array
    {
        $parts = array_values(array_filter(explode('/', $relativePath)));
        if ($parts === []) {
            return [null, null];
        }

        $project = $parts[0];
        if (preg_match('/\b(20\d{2})\b/', $project, $match)) {
            $year   = (int)$match[1];
            $school = trim(str_replace($match[0], '', $project)) ?: null;
            return [$school, $year];
        }

        return [$project, null];
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $full = $this->projectDir . '/' . ltrim($path, '/');
        return realpath($full) ?: $full;
    }
}
