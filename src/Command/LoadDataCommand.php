<?php

namespace App\Command;

use App\Entity\Audio;
use App\Entity\FileAsset;
use App\Entity\Song;
use App\Entity\SongLyrics;
use App\Entity\Video;
use App\Message\FetchYoutubeChannelMessage;
use App\Message\LoadSongsMessage;
use App\Repository\SongRepository;
use App\Service\SongMatcher;
use App\Services\AppService;
use App\Services\DocumentTextExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Survos\JsonlBundle\IO\JsonlReader;

#[AsCommand('app:load', 'load songs, videos, lyrics, and assets')]
class LoadDataCommand
{
    /** @var array<string, true> */
    private array $lyricsLinkSeen = [];

    public function __construct(
        private readonly EntityManagerInterface            $entityManager,
        private readonly ParameterBagInterface             $bag,
        private MessageBusInterface                        $bus,
        private readonly AppService                        $appService,
        private SongRepository                             $songRepository,
        private DocumentTextExtractor                      $documentTextExtractor,
        private readonly SongMatcher                      $songMatcher,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    )
    {
    }

    public function __invoke(
        SymfonyStyle    $io,
        #[Option(description: 'load video records')] ?bool $video = null,
        #[Option(description: 'load song records')] ?bool $songs = null,
        #[Option(description: 'load lyrics records')] ?bool $lyrics = null,
        #[Option(description: 'load file assets and audio')] ?bool $assets = null,
        #[Option(description: 'path to JSONL file for assets')] ?string $jsonl = null,
        #[Option(description: 'clear assets and audio first')] bool $reset = false,
        #[Option(description: 'max number of JSONL rows')] ?int $limit = null,
        #[Option(description: 'persist assets and audio')] bool $persist = true,
    ): int
    {
        $video ??= false;
        $songs ??= false;
        $lyrics ??= false;
        $assets ??= false;

        if ($assets) {
            $jsonlPath = $jsonl ?? 'data/seagate.jsonl';
            $this->loadFileAssetsFromJsonl($jsonlPath, $reset, $limit, $persist, $io);
        }

        if ($lyrics) {
            $this->loadLyricFiles($reset);
        }

        // should be first, so that video can look for it.
        if ($songs) {
            $this->appService->loadSongs($limit);;
//            $this->bus->dispatch(new LoadSongsMessage());
            $io->success('Songs Loaded ' . $this->songRepository->count() . ' songs');
        }

        if ($video) {
            $this->bus->dispatch(new FetchYoutubeChannelMessage(), [
//                new DeduplicateStamp('video-lock'),
            ]);
            $io->success('Videos Load Requested');
        }


        return Command::SUCCESS;
    }

    private function loadLyricFiles(bool $reset): void
    {
        $dir = $this->projectDir . '/../data/kpa/individual';
        $jsonLPath = $this->projectDir . '/data/lyrics.jsonl';

        if ($reset && file_exists($jsonLPath)) {
            unlink($jsonLPath);
        }

        if (!file_exists($jsonLPath)) {
            $this->appService->loadLyrics($dir, $jsonLPath);
        }

        foreach ($this->appService->eachLyricsFromJsonl($jsonLPath) as $lyrics) {
            // TODO: persist lyrics onto matching Song records
            unset($lyrics);
        }
    }

    private function loadFileAssetsFromJsonl(string $jsonlPath, bool $reset, ?int $limit, bool $persist, SymfonyStyle $io): void
    {
        if (!str_starts_with($jsonlPath, '/')) {
            $jsonlPath = $this->projectDir . '/' . ltrim($jsonlPath, '/');
        }
        if (!is_file($jsonlPath) || !is_readable($jsonlPath)) {
            $io->error(sprintf('JSONL not readable: %s', $jsonlPath));
            return;
        }

        $totalRows = $this->countJsonlRows($jsonlPath);
        if ($limit !== null) {
            $totalRows = min($totalRows, $limit);
        }

        $scanCount = 0;
        $audioScanCount = 0;
        $uniqueSongs = [];
        $knownTitles = [];
        $newSongCount = 0;

        $io->section('Scanning assets');
        $scanProgress = new ProgressBar($io, max($totalRows, 1));
        $scanProgress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        $scanProgress->setMessage('building song index');
        $scanProgress->start();

        $reader = JsonlReader::open($jsonlPath);
        foreach ($reader as $row) {
            if ($limit !== null && $scanCount >= $limit) {
                break;
            }

            $scanCount++;
            $path = (string)($row['path'] ?? '');
            $relativePath = (string)($row['relative_path'] ?? '');
            $filename = (string)($row['filename'] ?? '');
            $extension = $this->normalizeExtension((string)($row['extension'] ?? ''), $filename);
            $scanProgress->setMessage($this->progressLabel($relativePath, $path, $filename));

            if ($path === '' || $filename === '') {
                $scanProgress->advance();
                continue;
            }

            $type = $this->detectFileType($extension);
            if ($type !== 'audio') {
                $scanProgress->advance();
                continue;
            }

            $audioScanCount++;

            $title = $this->songMatcher->normalizeTitle($filename);
            [, $year] = $this->extractSchoolYear($relativePath);
            $key = mb_strtolower($title) . '|' . ($year ?? '');

            if (!isset($uniqueSongs[$key])) {
                $uniqueSongs[$key] = ['title' => $title, 'year' => $year];
                $knownTitles[$this->normalizeSongKey($title)] = true;
                if ($io->isVeryVerbose()) {
                    $io->text(sprintf('song: %s%s', $title, $year ? " ($year)" : ''));
                }
                $newSongCount++;
            }

            $scanProgress->advance();
        }

        $scanProgress->finish();
        $io->newLine(2);

        if (!$persist) {
            if ($reset) {
                $io->warning('reset is ignored in no-persist mode');
            }
            $io->success(sprintf('Indexed %d audio files, %d unique songs', $audioScanCount, count($uniqueSongs)));
            return;
        }

        if ($reset) {
            foreach ([Audio::class, FileAsset::class, Video::class, Song::class] as $class) {
                $deleted = $this->entityManager->createQuery('delete from ' . $class)->execute();
                $io->info($class . ' ' . $deleted);
            }
        }

        $fileAssetRepo = $this->entityManager->getRepository(FileAsset::class);
        $audioRepo = $this->entityManager->getRepository(Audio::class);
        $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);
        $audioCount = 0;
        $fileAssetCount = 0;
        $lyricsStats = [
            'attempted' => 0,
            'withCandidates' => 0,
            'skippedExpected' => 0,
            'failedUnexpected' => 0,
            'unreadable' => 0,
        ];
        $batchSize = 250;
        $pendingFlushCount = 0;
        $importCount = 0;

        // Warm SongMatcher cache once so per-row lookups are O(1) in-memory
        $this->songMatcher->warmCache();

        $io->section('Importing assets');
        $importProgress = new ProgressBar($io, max($totalRows, 1));
        $importProgress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        $importProgress->setMessage('persisting entities');
        $importProgress->start();

        $reader = JsonlReader::open($jsonlPath);
        foreach ($reader as $row) {
            if ($limit !== null && $importCount >= $limit) {
                break;
            }

            $importCount++;
            $path = (string)($row['path'] ?? '');
            $relativePath = (string)($row['relative_path'] ?? '');
            $filename = (string)($row['filename'] ?? '');
            $dirname = (string)($row['dirname'] ?? '');
            $extension = $this->normalizeExtension((string)($row['extension'] ?? ''), $filename);
            $size = isset($row['size']) ? (int)$row['size'] : null;
            $modifiedTime = isset($row['modified_time']) ? (int)$row['modified_time'] : null;
            $isReadable = (bool)($row['is_readable'] ?? true);
            [$school, $year] = $this->extractSchoolYear($relativePath);
            $mimeType = $this->guessMimeTypeFromExtension($extension);
            $importProgress->setMessage($this->progressLabel($relativePath, $path, $filename));

            if ($path === '' || $filename === '') {
                $importProgress->advance();
                continue;
            }

            $type = $this->detectFileType($extension);

            $fileAsset = $fileAssetRepo->findOneBy(['path' => $path]);
            if (!$fileAsset) {
                $fileAsset = new FileAsset(
                    path: $path,
                    relativePath: $relativePath,
                    filename: $filename,
                    extension: $extension,
                    dirname: $dirname,
                    size: $size,
                    modifiedTime: $modifiedTime,
                    isReadable: $isReadable,
                    type: $type,
                    school: $school,
                    year: $year,
                    mimeType: $mimeType,
                );
                $this->entityManager->persist($fileAsset);
                $pendingFlushCount++;
                $fileAssetCount++;
            } else {
                $fileAsset->school = $school;
                $fileAsset->year = $year;
                $fileAsset->mimeType = $mimeType;
            }

            if ($type === 'audio') {
                $audio = $audioRepo->findOneBy(['fileAsset' => $fileAsset]);
                if (!$audio) {
                    $title = $this->songMatcher->normalizeTitle($filename);
                    $song  = $this->songMatcher->findOrCreate($filename, $school, $year, persist: true);

                    // Merge aliases onto the song
                    $newAliases = $this->buildAliases($filename, $title);
                    $song->aliases = $song->aliases === null
                        ? $newAliases
                        : $this->mergeAliases($song->aliases, $newAliases);

                    $audio = new Audio(
                        fileAsset: $fileAsset,
                        song: $song,
                        title: $title,
                        format: $extension,
                        size: $size,
                        variant: $this->detectVariant($filename)
                    );
                    $this->entityManager->persist($audio);
                    $pendingFlushCount++;
                    $audioCount++;
                }
            } elseif ($type === 'lyrics') {
                if ($reset || $fileAsset->lyricsCandidates === null) {
                    $lyricsStats['attempted']++;
                    $candidates = $this->extractLyricsCandidates($path, $extension, $filename, $knownTitles, $io, $lyricsStats);
                    if ($candidates !== null) {
                        $lyricsStats['withCandidates']++;
                        $fileAsset->lyricsCandidates = $candidates;
                        if ($io->isVerbose()) {
                            $this->renderLyricsCandidatesPreview($fileAsset->relativePath ?: $fileAsset->path, $candidates, $io);
                        }
                    }
                }

                if (is_array($fileAsset->lyricsCandidates) && $fileAsset->lyricsCandidates !== []) {
                    $pendingFlushCount += $this->persistLyricsCandidates(
                        $fileAsset,
                        $fileAsset->lyricsCandidates,
                        $songLyricsRepo,
                    );
                }
            }

            $importProgress->advance();

            if ($pendingFlushCount >= $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->songMatcher->clearCache();
                $this->songMatcher->warmCache();
                $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);
                $this->lyricsLinkSeen = [];
                $pendingFlushCount = 0;
            }
        }

        if ($pendingFlushCount > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $importProgress->finish();
        $io->newLine(2);
        $io->success(sprintf('Imported %d file assets, %d audio records (%d unique song titles scanned)', $fileAssetCount, $audioCount, $newSongCount));
        $io->note([
            sprintf('Lyrics attempted: %d', $lyricsStats['attempted']),
            sprintf('Lyrics with candidates: %d', $lyricsStats['withCandidates']),
            sprintf('Lyrics skipped (expected parse issues): %d', $lyricsStats['skippedExpected']),
            sprintf('Lyrics failed (unexpected): %d', $lyricsStats['failedUnexpected']),
            sprintf('Lyrics unreadable files: %d', $lyricsStats['unreadable']),
        ]);
    }

    private function detectFileType(string $extension): string
    {
        // Audio: matches SongsTreeCommand::AUDIO_EXTS
        if (in_array($extension, ['mp3', 'm4a', 'wav', 'aif', 'aiff', 'flac', 'ogg', 'w64'], true)) {
            return 'audio';
        }
        // Video
        if (in_array($extension, ['mp4', 'mov', 'avi', 'wmv', 'mkv'], true)) {
            return 'video';
        }
        // Charts/notation: matches SongsTreeCommand::CHART_EXTS
        if (in_array($extension, ['pdf', 'musx', 'mus', 'sib', 'mxl', 'musicxml', 'xml', 'song', 'plj'], true)) {
            return 'chart';
        }
        // Lyrics: matches SongsTreeCommand::LYRICS_EXTS
        if (in_array($extension, ['doc', 'docx', 'txt', 'rtf', 'pages'], true)) {
            return 'lyrics';
        }
        return 'other';
    }

    private function normalizeExtension(string $extension, string $filename = ''): string
    {
        $extension = trim(strtolower($extension));
        if ($extension === '' && $filename !== '') {
            $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        }

        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
        if ($extension === '') {
            return 'unknown';
        }

        return mb_substr($extension, 0, 16);
    }

    private function guessMimeTypeFromExtension(string $extension): ?string
    {
        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'aif', 'aiff' => 'audio/aiff',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'w64' => 'audio/x-w64',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'mkv' => 'video/x-matroska',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            default => null,
        };
    }

    private function normalizeSongKey(string $title): string
    {
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        return mb_strtolower(trim($title));
    }

    /**
     * @param array<string, string|array<int, string>> $candidates
     */
    private function persistLyricsCandidates(FileAsset $fileAsset, array $candidates, ObjectRepository $songLyricsRepo): int
    {
        $created = 0;

        foreach ($candidates as $rawTitle => $lyrics) {
            $variants = is_array($lyrics) ? $lyrics : [$lyrics];

            foreach ($variants as $lyricsText) {
                $lyricsText = trim((string) $lyricsText);
                if ($lyricsText === '') {
                    continue;
                }

                $song = $this->songMatcher->findOrCreate(
                    $rawTitle,
                    $fileAsset->school,
                    $fileAsset->year,
                    persist: true,
                );

                $existing = $songLyricsRepo->findOneBy(['song' => $song, 'fileAsset' => $fileAsset]);
                $pairKey = ($song->id ?? ('new' . spl_object_id($song))) . ':' . $fileAsset->id;
                if (isset($this->lyricsLinkSeen[$pairKey])) {
                    continue;
                }
                if ($existing instanceof SongLyrics) {
                    if ($existing->lyricsText === null || mb_strlen($lyricsText) > mb_strlen((string) $existing->lyricsText)) {
                        $existing->lyricsText = $lyricsText;
                        $existing->sourceTitle = mb_substr($rawTitle, 0, 255);
                    }
                    $this->lyricsLinkSeen[$pairKey] = true;
                } else {
                    $this->entityManager->persist(new SongLyrics(
                        song: $song,
                        fileAsset: $fileAsset,
                        lyricsText: $lyricsText,
                        sourceTitle: mb_substr($rawTitle, 0, 255),
                    ));
                    $created++;
                    $this->lyricsLinkSeen[$pairKey] = true;
                }

                $existingLyrics = isset($song->lyrics) ? (string) $song->lyrics : '';
                if ($existingLyrics === '' || mb_strlen($lyricsText) > mb_strlen($existingLyrics)) {
                    $song->lyrics = $lyricsText;
                }
            }
        }

        return $created;
    }

    private function extractLyricsCandidates(
        string $path,
        string $extension,
        string $filename,
        array $knownTitles,
        SymfonyStyle $io,
        array &$lyricsStats,
    ): ?array {
        $fullPath = $this->resolvePath($path);
        $label = $this->progressLabel('', $path, $filename);
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            $lyricsStats['unreadable']++;
            $io->warning(sprintf('lyrics file not readable: %s', $label));
            return null;
        }

        try {
            $text = match ($extension) {
                'doc' => $this->documentTextExtractor->extractFromDoc($fullPath),
                default => $this->documentTextExtractor->extractFromDocx($fullPath),
            };
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if ($this->isExpectedLyricsParseNoise($message)) {
                $lyricsStats['skippedExpected']++;
                if ($io->isVerbose()) {
                    $io->text(sprintf('lyrics extraction skipped for %s: %s', $label, $message));
                }
            } else {
                $lyricsStats['failedUnexpected']++;
                $io->warning(sprintf('lyrics extraction failed for %s: %s', $label, $message));
            }
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($text) === '') {
            return null;
        }

        $candidates = [];
        $chunks = preg_split("/\f/u", $text) ?: [];
        if (!$chunks) {
            $chunks = [$text];
        }

        $fallbackTitle = pathinfo($filename, PATHINFO_FILENAME);
        foreach ($chunks as $chunk) {
            if (trim((string)$chunk) === '') {
                continue;
            }
            $chunkCandidates = $this->parseLyricsChunk((string)$chunk, $knownTitles, $fallbackTitle);
            foreach ($chunkCandidates as $title => $lyrics) {
                if (!isset($candidates[$title])) {
                    $candidates[$title] = $lyrics;
                } elseif (is_array($candidates[$title])) {
                    $candidates[$title][] = $lyrics;
                } else {
                    $candidates[$title] = [$candidates[$title], $lyrics];
                }
            }
        }

        return $candidates ?: null;
    }

    private function parseLyricsChunk(string $chunk, array $knownTitles, string $fallbackTitle): array
    {
        $lines = preg_split("/\n/u", $chunk) ?: [];
        $matched = [];
        $currentTitle = null;
        $buffer = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $normalized = $this->normalizeSongKey($trimmed);
            if ($trimmed !== '' && isset($knownTitles[$normalized])) {
                if ($currentTitle !== null) {
                    $matched[$currentTitle] = rtrim(implode("\n", $buffer));
                }
                $currentTitle = $trimmed;
                $buffer = [];
                continue;
            }

            if ($currentTitle !== null) {
                $buffer[] = $line;
            }
        }

        if ($currentTitle !== null) {
            $matched[$currentTitle] = rtrim(implode("\n", $buffer));
        }

        if ($matched) {
            return $matched;
        }

        $firstTitle = null;
        foreach ($lines as $idx => $line) {
            if (trim($line) === '') {
                continue;
            }
            $firstTitle = trim($line);
            $lyricsLines = array_slice($lines, $idx + 1);
            return [$firstTitle => rtrim(implode("\n", $lyricsLines))];
        }

        return [$fallbackTitle => rtrim($chunk)];
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $combined = $this->projectDir . '/' . ltrim($path, '/');
        return realpath($combined) ?: $combined;
    }

    private function countJsonlRows(string $jsonlPath): int
    {
        $rows = 0;
        $file = new \SplFileObject($jsonlPath, 'r');

        while (!$file->eof()) {
            $line = trim((string)$file->fgets());
            if ($line !== '') {
                $rows++;
            }
        }

        return $rows;
    }

    private function progressLabel(string $relativePath, string $path, string $filename): string
    {
        $label = trim($relativePath);
        if ($label === '') {
            $label = trim($path);
        }
        if ($label === '') {
            $label = trim($filename);
        }
        if ($label === '') {
            return 'processing';
        }

        if (mb_strlen($label) <= 80) {
            return $label;
        }

        return '...' . mb_substr($label, -77);
    }

    private function isExpectedLyricsParseNoise(string $message): bool
    {
        $message = strtolower($message);

        foreach ([
            'array to string conversion',
            'textrun could not be converted to string',
            'archive failed to load',
            'setcommentreference()',
            'invalid image:',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function renderLyricsCandidatesPreview(string $label, array $candidates, SymfonyStyle $io): void
    {
        $io->text(sprintf('lyrics candidates: %s', $label));
        foreach ($candidates as $title => $lyrics) {
            $snippet = is_array($lyrics) ? $lyrics[0] : $lyrics;
            $snippet = trim((string)$snippet);
            if ($snippet !== '') {
                $lines = preg_split('/\R/u', $snippet) ?: [];
                $lines = array_values(array_filter($lines, static fn(string $line) => trim($line) !== ''));
                $snippet = implode("\n", array_slice($lines, 0, 3));
            }
            $io->text(sprintf('  %s: %s', $title, $snippet !== '' ? $snippet : '<empty>'));
        }
    }

    private function buildAliases(string $filename, string $title): array
    {
        $rawTitle = trim(pathinfo($filename, PATHINFO_FILENAME));
        $aliases = [$title];
        if ($rawTitle !== '' && $rawTitle !== $title) {
            $aliases[] = $rawTitle;
        }
        return array_values(array_unique($aliases));
    }

    private function mergeAliases(array $existing, array $newAliases): array
    {
        return array_values(array_unique(array_merge($existing, $newAliases)));
    }

    private function detectVariant(string $filename): ?string
    {
        $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $tags = [];

        if (preg_match('/\bgv\b/u', $name)) {
            $tags[] = 'gv';
        }
        if (preg_match('/\bgtr\b|guitar/u', $name)) {
            $tags[] = 'gtr';
        }
        if (preg_match('/\bwf\b/u', $name)) {
            $tags[] = 'wf';
        }
        if (preg_match('/ai|arrangement|cover/u', $name)) {
            $tags[] = 'ai';
        }
        if (preg_match('/concert|live/u', $name)) {
            $tags[] = 'live';
        }

        $tags = array_values(array_unique($tags));
        return $tags ? implode(',', $tags) : null;
    }

    private function extractSchoolYear(string $relativePath): array
    {
        $parts = array_values(array_filter(explode('/', $relativePath)));
        if (!$parts) {
            return [null, null];
        }

        $project = $parts[0];
        if (preg_match('/\b(20\d{2})\b/', $project, $match)) {
            $year = (int)$match[1];
            $school = trim(str_replace($match[0], '', $project)) ?: null;
            return [$school, $year];
        }

        return [$project, null];
    }
}
