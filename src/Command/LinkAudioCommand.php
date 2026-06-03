<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SongMatcher;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand('app:link-audio', 'Read a JSONL audio scan and symlink matched files into var/data/<song-slug>/')]
final class LinkAudioCommand
{
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'aif', 'aiff', 'flac', 'm4a', 'ogg', 'wma'];

    public function __construct(
        private readonly SongMatcher $songMatcher,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('path to JSONL file from import:dir')]
        string $jsonl = 'data/audio.jsonl',
        #[Option('staging directory for symlinks')]
        string $staging = 'var/data',
        #[Option('only process first N rows (0 = all)')]
        int $limit = 0,
        #[Option('show matches without creating symlinks')]
        bool $dry = false,
    ): int {
        $jsonlPath = str_starts_with($jsonl, '/') ? $jsonl : $this->projectDir . '/' . ltrim($jsonl, '/');
        if (!is_file($jsonlPath)) {
            $io->error(sprintf('JSONL not readable: %s', $jsonlPath));
            return Command::FAILURE;
        }

        $stagingDir = str_starts_with($staging, '/') ? $staging : $this->projectDir . '/' . ltrim($staging, '/');
        if (!is_dir($stagingDir)) {
            $io->error(sprintf('Staging directory does not exist: %s', $stagingDir));
            return Command::FAILURE;
        }

        $this->songMatcher->warmCache();

        $matched = 0;
        $unmatched = 0;
        $skipped = 0;
        $count = 0;
        $rows = [];
        $linkedPaths = [];

        $handle = fopen($jsonlPath, 'r');
        if ($handle === false) {
            $io->error('Cannot open JSONL file');
            return Command::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }

            // import:dir format nests file info; legacy flat JSONL doesn't
            $fi = $row['file_info'] ?? $row;
            $filename = $fi['filename'] ?? null;
            if ($filename === null || $filename === '') {
                continue;
            }
            if (($row['type'] ?? null) === 'DIR') {
                continue;
            }

            $ext = strtolower($fi['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, self::AUDIO_EXTENSIONS, true)) {
                continue;
            }

            if ($limit > 0 && $count >= $limit) {
                break;
            }
            $count++;

            $stem = pathinfo($filename, PATHINFO_FILENAME);
            $relativePath = $fi['relative_pathname'] ?? $row['relative_path'] ?? $filename;

            $song = $this->songMatcher->find($stem);
            if ($song === null) {
                $unmatched++;
                if ($io->isVerbose()) {
                    $rows[] = ['miss', $stem, '-', $relativePath];
                }
                continue;
            }

            $songSlug = $this->slugifyTitle((string) $song->title);
            $songDir = $stagingDir . '/' . $songSlug;
            $variant = self::detectVariant($stem);
            $label = $variant ?? 'recording';
            $symlinkName = $this->uniqueSymlinkName($songDir, $songSlug, $label, $ext, $linkedPaths);

            $sourcePath = $fi['pathname'] ?? $row['path'] ?? null;
            if ($sourcePath === null) {
                $skipped++;
                continue;
            }

            if (!str_starts_with($sourcePath, '/')) {
                $sourcePath = $this->projectDir . '/' . ltrim($sourcePath, '/');
            }

            // Extract probe metadata (duration, etc.) from JSONL
            $meta = $row['metadata'] ?? [];
            $probeDuration = isset($meta['media_duration']) ? (float) $meta['media_duration'] : null;

            if (!$dry) {
                $this->filesystem->mkdir($songDir);
                $target = $songDir . '/' . $symlinkName;
                if (!file_exists($target)) {
                    $this->filesystem->symlink($sourcePath, $target);
                }

                // Store probe metadata so ingest-audio can read duration without re-probing
                $audioMetaPath = $songDir . '/' . $songSlug . '-audio-meta.json';
                $audioMeta = [];
                if (is_file($audioMetaPath)) {
                    $audioMeta = json_decode((string) file_get_contents($audioMetaPath), true) ?? [];
                }
                $audioMeta[$symlinkName] = array_filter([
                    'duration' => $probeDuration,
                    'size' => $fi['size'] ?? $meta['size'] ?? null,
                    'mimeType' => $meta['mime_type'] ?? null,
                    'originalFilename' => $filename,
                ]);
                $this->filesystem->dumpFile(
                    $audioMetaPath,
                    json_encode($audioMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                );
            }

            $matched++;
            $rows[] = ['link', $song->title, $label . '.' . $ext, $relativePath];
            $linkedPaths[$songDir . '/' . $symlinkName] = true;
        }

        fclose($handle);

        if ($rows !== []) {
            $io->table(['action', 'song', 'linked as', 'source'], $rows);
        }

        $io->success(sprintf(
            '%s — matched: %d, unmatched: %d, skipped: %d (of %d audio rows)',
            $dry ? 'DRY RUN' : 'Done',
            $matched,
            $unmatched,
            $skipped,
            $count,
        ));

        return Command::SUCCESS;
    }

    private function slugifyTitle(string $title): string
    {
        $clean = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $clean = (string) transliterator_transliterate('Any-Latin; Latin-ASCII;', $clean);
        }
        $clean = (string) preg_replace("/'+/", '', $clean);

        return mb_strtolower((string) (new AsciiSlugger())->slug($clean));
    }

    private function uniqueSymlinkName(string $songDir, string $slug, string $label, string $ext, array &$taken): string
    {
        $candidate = sprintf('%s-%s.%s', $slug, $label, $ext);
        $full = $songDir . '/' . $candidate;
        if (!isset($taken[$full]) && !file_exists($full)) {
            return $candidate;
        }

        $n = 2;
        do {
            $candidate = sprintf('%s-%s-%d.%s', $slug, $label, $n, $ext);
            $full = $songDir . '/' . $candidate;
            $n++;
        } while (isset($taken[$full]) || file_exists($full));

        return $candidate;
    }

    private static function detectVariant(string $filename): ?string
    {
        $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $tags = [];

        if (preg_match('/\b(?:gv|guide[_\s-]?vocal|guide[_\s-]?vox)\b/', $name)) {
            $tags[] = 'gv';
        }
        if (preg_match('/\b(?:gtr(?:\s*only)?|guitar(?:\s*only)?)\b/', $name)) {
            $tags[] = 'gtr';
        }
        if (preg_match('/\bwf(?:ly)?\b/', $name)) {
            $tags[] = 'wf';
        }
        if (preg_match('/\b(?:inst(?:rumental)?)\b/', $name)) {
            $tags[] = 'instrumental';
        }
        if (preg_match('/\bdemo\b/', $name)) {
            $tags[] = 'demo';
        }
        if (preg_match('/\b(?:concert|live)\b/', $name)) {
            $tags[] = 'live';
        }
        if (preg_match('/\bbacking[_\s-]?track\b/', $name)) {
            $tags[] = 'backing';
        }
        if (preg_match('/\bai[_\s-]?(?:version)?\b/', $name)) {
            $tags[] = 'ai';
        }

        return $tags !== [] ? implode('-', $tags) : null;
    }
}
