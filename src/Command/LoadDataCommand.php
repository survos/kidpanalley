<?php

namespace App\Command;

use App\Entity\Audio;
use App\Entity\FileAsset;
use App\Entity\Song;
use App\Entity\Video;
use App\Message\FetchYoutubeChannelMessage;
use App\Message\LoadSongsMessage;
use App\Repository\SongRepository;
use App\Services\AppService;
use App\Services\DocumentTextExtractor;
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
use Survos\JsonlBundle\IO\JsonlReader;

#[AsCommand('app:load', 'load songs, videos, lyrics, and assets')]
class LoadDataCommand
{
    public function __construct(
        private readonly EntityManagerInterface            $entityManager,
        private readonly ParameterBagInterface             $bag,
        private MessageBusInterface                        $bus,
        private readonly AppService                        $appService,
        private SongRepository                             $songRepository,
        private DocumentTextExtractor                      $documentTextExtractor,
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
        $assets ??= true;

        if ($assets) {
            $jsonlPath = $jsonl ?? 'data/files.jsonl';
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

    private function loadLyricFiles(bool $reset)
    {

        $dir = $this->projectDir . '/../data/kpa/individual';
        $jsonLPath = 'data/lyrics.jsonl';
        if ($reset && file_exists($jsonLPath)) {
            unlink($jsonLPath);
        }
        if (!file_exists($jsonLPath)) {
            $this->appService->loadLyrics($dir, $jsonLPath);
        }
        foreach ($this->appService->eachLyricsFromJsonl($jsonLPath) as $lyrics) {
            dump($lyrics);
            break;
        }

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
                $song->lyrics = $text;
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

    private function loadFileAssetsFromJsonl(string $jsonlPath, bool $reset, ?int $limit, bool $persist, SymfonyStyle $io): void
    {
        if (!str_starts_with($jsonlPath, '/')) {
            $jsonlPath = $this->projectDir . '/' . ltrim($jsonlPath, '/');
        }
        if (!is_file($jsonlPath) || !is_readable($jsonlPath)) {
            $io->error(sprintf('JSONL not readable: %s', $jsonlPath));
            return;
        }
        $count = 0;
        $uniqueSongs = [];
        $knownTitles = [];
        $newSongCount = 0;

        $reader = JsonlReader::open($jsonlPath);
        foreach ($reader as $row) {
            $path = (string)($row['path'] ?? '');
            $relativePath = (string)($row['relative_path'] ?? '');
            $filename = (string)($row['filename'] ?? '');
            $extension = strtolower((string)($row['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION)));

            if ($path === '' || $filename === '') {
                continue;
            }

            $type = $this->detectFileType($extension);
            if ($type !== 'audio') {
                if ($io->isVerbose()) {
                    $io->text(sprintf('skip %s -> %s', $relativePath ?: $path, $type));
                }
                $count++;
                if ($limit !== null && $count >= $limit) {
                    break;
                }
                continue;
            }

            $title = $this->normalizeTitle($filename);
            [, $year] = $this->extractSchoolYear($relativePath);
            $key = mb_strtolower($title) . '|' . ($year ?? '');

            if (!isset($uniqueSongs[$key])) {
                $uniqueSongs[$key] = ['title' => $title, 'year' => $year];
                $knownTitles[$this->normalizeSongKey($title)] = true;
                $io->text(sprintf('song: %s%s', $title, $year ? " ($year)" : ''));
                $newSongCount++;
            }

            if ($io->isVerbose()) {
                $io->text(sprintf('map: %s -> %s%s', $relativePath ?: $path, $title, $year ? " ($year)" : ''));
            }

            $count++;
            if ($limit !== null && $count >= $limit) {
                break;
            }
        }

        if (!$persist) {
            if ($reset) {
                $io->warning('reset is ignored in no-persist mode');
            }
            $io->success(sprintf('Listed %d audio files, %d unique songs', $count, count($uniqueSongs)));
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
        $audioCount = 0;
        $songCache = [];

        $reader = JsonlReader::open($jsonlPath);
        foreach ($reader as $row) {
            $path = (string)($row['path'] ?? '');
            $relativePath = (string)($row['relative_path'] ?? '');
            $filename = (string)($row['filename'] ?? '');
            $dirname = (string)($row['dirname'] ?? '');
            $extension = strtolower((string)($row['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION)));
            $size = isset($row['size']) ? (int)$row['size'] : null;
            $modifiedTime = isset($row['modified_time']) ? (int)$row['modified_time'] : null;
            $isReadable = (bool)($row['is_readable'] ?? true);

            if ($path === '' || $filename === '') {
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
                );
                $this->entityManager->persist($fileAsset);
            }

            if ($type === 'audio') {
                $audio = $audioRepo->findOneBy(['fileAsset' => $fileAsset]);
                if (!$audio) {
                    $title = $this->normalizeTitle($filename);
                    [$school, $year] = $this->extractSchoolYear($relativePath);

                    $code = Song::createCode($title, $school, $year);
                    $song = $songCache[$code] ?? null;
                    if (!$song) {
                        $song = $this->songRepository->findOneBy(['code' => $code]);
                        if (!$song) {
                            $song = new Song($code);
                            $song->title = $title;
                            $song->school = $school;
                            $song->year = $year;
                            $song->aliases = $this->buildAliases($filename, $title);
                            $this->entityManager->persist($song);
                        }
                        $songCache[$code] = $song;
                    }

                    if ($song->aliases === null) {
                        $song->aliases = $this->buildAliases($filename, $title);
                    } else {
                        $song->aliases = $this->mergeAliases($song->aliases, $this->buildAliases($filename, $title));
                    }

                    $audio = new Audio(
                        fileAsset: $fileAsset,
                        song: $song,
                        title: $title,
                        format: $extension,
                        size: $size,
                        variant: $this->detectVariant($filename)
                    );
                    $this->entityManager->persist($audio);
                    $audioCount++;
                }
            } elseif ($type === 'lyrics') {
                if ($reset || $fileAsset->lyricsCandidates === null) {
                    $candidates = $this->extractLyricsCandidates($path, $extension, $filename, $knownTitles, $io);
                    if ($candidates !== null) {
                        $fileAsset->lyricsCandidates = $candidates;
                        if ($io->isVerbose()) {
                            $this->renderLyricsCandidatesPreview($fileAsset->relativePath ?: $fileAsset->path, $candidates, $io);
                        }
                    }
                }
            }

            $count++;
            if ($limit !== null && $count >= $limit) {
                break;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('Imported %d file assets, %d audio records, %d new songs', $count, $audioCount, $newSongCount));
    }

    private function detectFileType(string $extension): string
    {
        return match ($extension) {
            'mp3', 'm4a', 'wav' => 'audio',
            'mp4', 'mov', 'avi' => 'video',
            'pdf' => 'chart',
            'doc', 'docx' => 'lyrics',
            default => 'other',
        };
    }

    private function normalizeSongKey(string $title): string
    {
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        return mb_strtolower(trim($title));
    }

    private function extractLyricsCandidates(
        string $path,
        string $extension,
        string $filename,
        array $knownTitles,
        SymfonyStyle $io
    ): ?array {
        $fullPath = $this->resolvePath($path);
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            $io->warning(sprintf('lyrics file not readable: %s', $fullPath));
            return null;
        }

        try {
            $text = match ($extension) {
                'doc' => $this->documentTextExtractor->extractFromDoc($fullPath),
                default => $this->documentTextExtractor->extractFromDocx($fullPath),
            };
        } catch (\Throwable $exception) {
            $io->warning(sprintf('lyrics extraction failed: %s', $exception->getMessage()));
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

    private function normalizeTitle(string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $baseName = preg_replace('/\([^)]*\)/', '', $baseName) ?? $baseName;
        $baseName = preg_replace('/^\d+\.\s*/', '', $baseName) ?? $baseName;
        $baseName = str_replace(['_', '-'], ' ', $baseName);

        $suffixes = [
            'gtr', 'gv', 'wf', 'guitar', 'guide vocal', 'vocal',
            'guitar only', 'backing track', 'instrumental',
            'ai version', 'ai', 'arrangement', 'cover',
            'drums', 'bass', 'other', 'remastered',
            'concert', 'live',
        ];

        $suffixPattern = implode('|', array_map(static fn(string $suffix) => preg_quote($suffix, '/'), $suffixes));
        $baseName = preg_replace('/\s*[-_\s]+(' . $suffixPattern . ')\s*$/i', '', $baseName) ?? $baseName;

        return trim(preg_replace('/\s+/', ' ', $baseName) ?? $baseName);
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
