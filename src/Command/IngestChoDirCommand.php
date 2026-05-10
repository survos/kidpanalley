<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Song;
use App\Service\SongMatcher;
use ChordPro\Line\Metadata;
use ChordPro\Parser as ChordProParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

#[AsCommand('app:ingest-cho-dir', 'Walk a directory of <slug>/<slug>-lyrics.cho files and create/update Song records from the ChordPro directives')]
final class IngestChoDirCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SongMatcher $songMatcher,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('directory containing <slug>/<slug>-lyrics.cho')]
        string $dir = 'var/data',
        #[Option('only ingest first N files (0 = all)')]
        int $limit = 0,
        #[Option('do not flush; show what would happen')]
        bool $dry = false,
    ): int {
        $absDir = str_starts_with($dir, '/') ? $dir : $this->projectDir . '/' . ltrim($dir, '/');
        if (!is_dir($absDir)) {
            $io->error(sprintf('Not a directory: %s', $absDir));
            return Command::FAILURE;
        }

        $finder = (new Finder())
            ->files()
            ->in($absDir)
            ->name('*-lyrics.cho')
            ->sortByName();

        $total = iterator_count($finder->getIterator());
        if ($total === 0) {
            $io->warning(sprintf('No *-lyrics.cho files under %s', $absDir));
            return Command::SUCCESS;
        }

        $this->songMatcher->warmCache();
        $parser = new ChordProParser();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rows = [];
        $count = 0;

        foreach ($finder as $file) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }
            $count++;

            $cho = file_get_contents($file->getPathname());
            if ($cho === false || trim($cho) === '') {
                $skipped++;
                continue;
            }

            $directives = $this->extractDirectives($parser, $cho);
            $title = $directives['title'] ?? null;
            if ($title === null || trim($title) === '') {
                $skipped++;
                $io->warning(sprintf('No {title:} in %s', $file->getRelativePathname()));
                continue;
            }

            $school = $directives['school'] ?? null;
            $year = isset($directives['year']) && is_numeric($directives['year'])
                ? (int) $directives['year']
                : null;

            $metadata = $this->loadMetadataSidecar($file->getPath(), $file->getFilename());

            // metadata.json overrides .cho directives where both are present
            $effectiveSchool = $metadata['school'] ?? $school;
            $effectiveYear = isset($metadata['year']) && is_numeric($metadata['year'])
                ? (int) $metadata['year']
                : $year;

            $song = $this->songMatcher->findOrCreate(
                rawTitle: $title,
                school: $effectiveSchool,
                year: $effectiveYear,
                persist: !$dry,
            );
            $existed = isset($song->id);

            $this->applyToSong($song, $cho, $effectiveSchool, $effectiveYear, $metadata);

            $existed ? $updated++ : $created++;

            $rows[] = [
                $existed ? 'upd' : 'new',
                $title,
                $effectiveSchool ?? '-',
                $effectiveYear !== null ? (string) $effectiveYear : '-',
                $metadata !== [] ? '✓' : '-',
                $file->getRelativePathname(),
            ];
        }

        if (!$dry) {
            $this->entityManager->flush();
        }

        $io->table(['action', 'title', 'school', 'year', 'meta', 'file'], $rows);
        $io->success(sprintf(
            '%s — created: %d, updated: %d, skipped: %d (of %d found)',
            $dry ? 'DRY RUN' : 'Done',
            $created,
            $updated,
            $skipped,
            $total,
        ));

        return Command::SUCCESS;
    }

    /**
     * Field names captured in Song.notes as a JSON blob (those with no dedicated column).
     */
    private const NOTES_FIELDS = [
        'record_status', 'iswc', 'isrc', 'tuning', 'ascap', 'copyright',
        'midi', 'category', 'assist', 'notes',
    ];

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyToSong(Song $song, string $cho, ?string $school, ?int $year, array $metadata): void
    {
        $song->lyrics = $cho;

        if ($school !== null) {
            $song->school = $school;
        }

        if ($year !== null) {
            $song->year = $year;
        }

        if ($metadata === []) {
            return;
        }

        $writers = $this->buildWriters($metadata);
        if ($writers !== '') {
            $song->writers = $writers;
        }

        if (!empty($metadata['publisher'])) {
            $song->publisher = (string) $metadata['publisher'];
        }
        if (!empty($metadata['recording'])) {
            $song->recording = (string) $metadata['recording'];
        }

        if (!empty($metadata['date']) && is_string($metadata['date'])) {
            try {
                // Song.date is a DateType column → must be \DateTime, not \DateTimeImmutable
                $song->date = new \DateTime($metadata['date']);
                if ($song->year === null) {
                    $song->year = (int) $song->date->format('Y');
                }
            } catch (\Throwable) {
                // leave unset
            }
        }

        $notesBag = [];
        foreach (self::NOTES_FIELDS as $field) {
            if (!empty($metadata[$field])) {
                $notesBag[$field] = $metadata[$field];
            }
        }
        if ($notesBag !== []) {
            $song->notes = json_encode($notesBag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * Slash-joins writer / co_writer / co_writer_assistant per Song::getWritersArray() convention.
     *
     * @param array<string, mixed> $metadata
     */
    private function buildWriters(array $metadata): string
    {
        $parts = [];
        foreach (['writer', 'co_writer', 'co_writer_assistant'] as $field) {
            $v = trim((string) ($metadata[$field] ?? ''));
            if ($v !== '' && !in_array($v, $parts, true)) {
                $parts[] = $v;
            }
        }
        return implode('/', $parts);
    }

    /**
     * Read <slug>-metadata.json sitting next to <slug>-lyrics.cho.
     *
     * @return array<string, mixed>
     */
    private function loadMetadataSidecar(string $songDir, string $lyricsFilename): array
    {
        $stem = pathinfo($lyricsFilename, PATHINFO_FILENAME);  // "<slug>-lyrics"
        $slug = (string) preg_replace('/-lyrics$/', '', $stem);
        $path = $songDir . '/' . $slug . '-metadata.json';

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, string>
     */
    private function extractDirectives(ChordProParser $parser, string $cho): array
    {
        try {
            $song = $parser->parse($cho);
        } catch (\Throwable) {
            return [];
        }

        $directives = [];
        foreach ($song->getLines() as $line) {
            if ($line instanceof Metadata) {
                $directives[$line->getName()] = (string) $line->getValue();
            }
        }
        return $directives;
    }
}
