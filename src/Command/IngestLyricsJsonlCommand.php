<?php

namespace App\Command;

use App\Entity\FileAsset;
use App\Entity\SongLyrics;
use App\Service\SongMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Survos\JsonlBundle\IO\JsonlReader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:ingest-lyrics-jsonl', 'Map extracted lyrics JSONL into Song + SongLyrics records')]
final class IngestLyricsJsonlCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SongMatcher $songMatcher,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('lyrics JSONL path')]
        string $jsonl = 'data/lyrics.jsonl',
        #[Option('clear existing SongLyrics before ingest')]
        bool $reset = false,
        #[Option('max JSONL rows')]
        ?int $limit = null,
    ): int {
        $path = str_starts_with($jsonl, '/') ? $jsonl : $this->projectDir . '/' . ltrim($jsonl, '/');
        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('JSONL not readable: %s', $path));
            return Command::FAILURE;
        }

        if ($reset) {
            $this->entityManager->createQuery('DELETE FROM App\\Entity\\SongLyrics sl')->execute();
        }

        $fileAssetRepo = $this->entityManager->getRepository(FileAsset::class);
        $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);

        $lyricsAssets = $fileAssetRepo->findBy(['type' => 'lyrics']);
        $assetByStem = [];
        foreach ($lyricsAssets as $asset) {
            $stem = mb_strtolower(pathinfo((string) $asset->filename, PATHINFO_FILENAME));
            $assetByStem[$stem] ??= [];
            $assetByStem[$stem][] = $asset;
        }

        $this->songMatcher->warmCache();

        $rows = 0;
        $createdLinks = 0;
        $updatedSongs = 0;
        $skippedNoFile = 0;
        $seenPairs = [];

        $reader = JsonlReader::open($path);
        foreach ($reader as $row) {
            $rows++;
            if ($limit !== null && $rows > $limit) {
                break;
            }

            $sourceFile = trim((string) ($row['file'] ?? ''));
            $lyricsLines = $row['lyrics'] ?? null;
            if ($sourceFile === '' || !is_array($lyricsLines)) {
                continue;
            }

            $lyricsText = $this->joinLyrics($lyricsLines);
            if ($lyricsText === '') {
                continue;
            }

            $sourceStem = mb_strtolower(pathinfo($sourceFile, PATHINFO_FILENAME));
            $candidates = $assetByStem[$sourceStem] ?? [];
            if ($candidates === []) {
                $skippedNoFile++;
                continue;
            }

            $title = $this->firstNonEmptyLine($lyricsLines) ?: pathinfo($sourceFile, PATHINFO_FILENAME);
            $song = $this->songMatcher->findOrCreate($title, persist: true);

            $existingLyrics = isset($song->lyrics) ? (string) $song->lyrics : '';
            if ($existingLyrics === '' || mb_strlen($lyricsText) > mb_strlen($existingLyrics)) {
                $song->lyrics = $lyricsText;
                $updatedSongs++;
            }

            foreach ($candidates as $fileAsset) {
                $pairKey = ($song->id ?? ('new' . spl_object_id($song))) . ':' . $fileAsset->id;
                if (isset($seenPairs[$pairKey])) {
                    continue;
                }
                $existing = $songLyricsRepo->findOneBy(['song' => $song, 'fileAsset' => $fileAsset]);
                if ($existing instanceof SongLyrics) {
                    $seenPairs[$pairKey] = true;
                    continue;
                }

                $link = new SongLyrics(
                    song: $song,
                    fileAsset: $fileAsset,
                    lyricsText: $lyricsText,
                    sourceTitle: mb_substr($title, 0, 255),
                );
                $this->entityManager->persist($link);
                $createdLinks++;
                $seenPairs[$pairKey] = true;
            }

            if ($rows % 200 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->songMatcher->clearCache();
                $this->songMatcher->warmCache();
                $songLyricsRepo = $this->entityManager->getRepository(SongLyrics::class);
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Processed %d rows, created %d song-file lyrics links, updated %d song lyrics, skipped %d rows with no matching lyrics file',
            $rows,
            $createdLinks,
            $updatedSongs,
            $skippedNoFile,
        ));

        return Command::SUCCESS;
    }

    private function joinLyrics(array $lines): string
    {
        $normalized = array_map(static fn($line): string => trim((string) $line), $lines);
        $normalized = array_values(array_filter($normalized, static fn(string $line): bool => $line !== ''));
        return trim(implode("\n", $normalized));
    }

    private function firstNonEmptyLine(array $lines): ?string
    {
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                return $line;
            }
        }
        return null;
    }
}
