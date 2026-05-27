<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SongMatcher;
use ChordPro\Line\Metadata;
use ChordPro\Parser as ChordProParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand('app:enrich-song-dirs-from-csv', 'For each <slug>/<slug>-lyrics.cho in the song-archive, look up the song in the master CSV and write <slug>/<slug>-metadata.json (only when matched)')]
final class EnrichSongDirsFromCsvCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly AsciiSlugger $slugger = new AsciiSlugger(),
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('song-archive directory (each <slug>/ contains <slug>-lyrics.cho)')]
        string $dir = 'var/data',
        #[Option('master CSV path')]
        string $csv = 'data/master-song-list.csv',
        #[Option('overwrite existing -metadata.json files')]
        bool $force = false,
        #[Option('dry run — count matches without writing JSON')]
        bool $dry = false,
        #[Option('only show miss rows in the output table (still writes JSON for matches)')]
        bool $missedOnly = false,
        #[Option('create song dirs + metadata for CSV rows that have no .cho yet')]
        bool $createDirs = false,
    ): int {
        $absDir = str_starts_with($dir, '/') ? $dir : $this->projectDir . '/' . ltrim($dir, '/');
        $absCsv = str_starts_with($csv, '/') ? $csv : $this->projectDir . '/' . ltrim($csv, '/');

        if (!is_dir($absDir)) {
            $io->error(sprintf('Not a directory: %s', $absDir));
            return Command::FAILURE;
        }
        if (!is_file($absCsv) || !is_readable($absCsv)) {
            $io->error(sprintf('CSV not readable: %s', $absCsv));
            return Command::FAILURE;
        }

        $byKey = $this->loadCsvIndex($absCsv);
        $io->note(sprintf('Indexed %d CSV rows by match key', count($byKey)));

        $finder = (new Finder())
            ->files()
            ->in($absDir)
            ->name('*-lyrics.cho')
            ->sortByName();

        $parser = new ChordProParser();

        $matched = 0;
        $unmatched = 0;
        $skipped = 0;
        $written = 0;
        $rows = [];
        $matchedKeys = [];

        foreach ($finder as $file) {
            $cho = file_get_contents($file->getPathname());
            if ($cho === false || trim($cho) === '') {
                $skipped++;
                continue;
            }

            $title = $this->extractTitle($parser, $cho);
            if ($title === null) {
                $skipped++;
                $rows[] = ['skip', '?', '(no title in cho)', $file->getRelativePathname()];
                continue;
            }

            $key = SongMatcher::matchKey($title);
            $row = $byKey[$key] ?? null;

            if ($row === null) {
                $unmatched++;
                $rows[] = ['miss', $title, '-', $file->getRelativePathname()];
                continue;
            }

            $matched++;
            $matchedKeys[$key] = true;
            $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $slug = (string) preg_replace('/-lyrics$/', '', $stem);
            $metadataPath = $file->getPath() . '/' . $slug . '-metadata.json';

            if (file_exists($metadataPath) && !$force) {
                $rows[] = ['exists', $title, $row['school'] ?? '-', basename($metadataPath)];
                continue;
            }

            if (!$dry) {
                $payload = $this->buildMetadataPayload($row, $title, $key, $absCsv);
                $this->filesystem->dumpFile(
                    $metadataPath,
                    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                );
                $written++;
            }
            $rows[] = ['write', $title, $row['school'] ?? '-', basename($metadataPath)];
        }

        $created = 0;
        if ($createDirs) {
            foreach ($byKey as $key => $csvRow) {
                if (isset($matchedKeys[$key])) {
                    continue;
                }
                $title = trim((string) ($csvRow['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $slug = $this->slugifyTitle($title);
                if ($slug === '') {
                    continue;
                }

                $songDir = $absDir . '/' . $slug;
                $metadataPath = $songDir . '/' . $slug . '-metadata.json';

                if (file_exists($metadataPath) && !$force) {
                    $rows[] = ['exists', $title, $csvRow['school'] ?? '-', $slug . '/'];
                    continue;
                }

                if (!$dry) {
                    $this->filesystem->mkdir($songDir);
                    $payload = $this->buildMetadataPayload($csvRow, $title, (string) $key, $absCsv);
                    $this->filesystem->dumpFile(
                        $metadataPath,
                        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                    );
                }
                $created++;
                $written++;
                $rows[] = ['create', $title, $csvRow['school'] ?? '-', $slug . '/'];
            }
        }

        $tableRows = $missedOnly
            ? array_values(array_filter($rows, static fn(array $r): bool => $r[0] === 'miss'))
            : $rows;
        if (count($tableRows) <= 200) {
            $io->table(['action', 'title', 'school', 'file'], $tableRows);
        }
        $io->success(sprintf(
            '%s — matched: %d, unmatched: %d, skipped: %d, written: %d, dirs created: %d',
            $dry ? 'DRY RUN' : 'Done',
            $matched,
            $unmatched,
            $skipped,
            $written,
            $created,
        ));

        if ($unmatched > 0) {
            $io->note('Unmatched .cho files have no row in the master CSV. Either the title was edited in the .docx or the song is missing from the spreadsheet (see project_master_xlsx_other_tabs).');
        }

        return Command::SUCCESS;
    }

    private function slugifyTitle(string $title): string
    {
        $clean = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $clean = (string) transliterator_transliterate('Any-Latin; Latin-ASCII;', $clean);
        }
        $clean = (string) preg_replace("/'+/", '', $clean);

        return mb_strtolower((string) $this->slugger->slug($clean));
    }

    private function extractTitle(ChordProParser $parser, string $cho): ?string
    {
        try {
            $song = $parser->parse($cho);
        } catch (\Throwable) {
            return null;
        }
        foreach ($song->getLines() as $line) {
            if ($line instanceof Metadata && $line->getName() === 'title') {
                $value = trim((string) $line->getValue());
                return $value === '' ? null : $value;
            }
        }
        return null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function loadCsvIndex(string $csv): array
    {
        $fp = fopen($csv, 'r');
        if ($fp === false) {
            return [];
        }
        $headers = fgetcsv($fp) ?: [];
        $byKey = [];
        while (($row = fgetcsv($fp)) !== false) {
            $assoc = @array_combine($headers, $row);
            if (!is_array($assoc)) {
                continue;
            }
            $title = trim((string) ($assoc['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $key = SongMatcher::matchKey($title);
            // First win — if duplicate titles exist, later rows are dropped (master CSV has many same-title entries from different schools)
            $byKey[$key] ??= $assoc;
        }
        fclose($fp);
        return $byKey;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function buildMetadataPayload(array $row, string $title, string $matchKey, string $csvPath): array
    {
        $payload = ['title' => $title, 'matchKey' => $matchKey];
        foreach ($row as $k => $v) {
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            // Coerce year to int if it looks like one
            if ($k === 'year' && ctype_digit($v)) {
                $payload[$k] = (int) $v;
                continue;
            }
            $payload[$k] = $v;
        }
        $payload['_source'] = [
            'csv' => str_replace($this->projectDir . '/', '', $csvPath),
            'matchedAt' => date('c'),
        ];
        return $payload;
    }
}
