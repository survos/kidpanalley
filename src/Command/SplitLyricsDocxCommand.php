<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Lyrics\BodyLine;
use App\Dto\Lyrics\DocxParagraph;
use App\Dto\Lyrics\ExtractedSong;
use App\Dto\Lyrics\ParseTrace;
use App\Dto\Lyrics\SongPart;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Paragraph;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand('app:split-lyrics-docx', 'Split a multi-song .docx into one ChordPro (.cho) file per song using the docx paragraph styles')]
final class SplitLyricsDocxCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly SluggerInterface $slugger = new AsciiSlugger(),
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('path to a .docx file OR a directory of .docx files (recurses)')]
        string $path,
        #[Option('output directory for .cho files (created if missing)')]
        string $outputDir = 'var/data',
        #[Option('overwrite existing .cho files')]
        bool $force = false,
        #[Option('print first song contents (default true in single-file mode, false in dir mode; pass --no-preview in bulk runs)')]
        ?bool $preview = null,
    ): int {
        if (!is_readable($path)) {
            $io->error(sprintf('Path not readable: %s', $path));
            return Command::FAILURE;
        }

        $absOutputDir = str_starts_with($outputDir, '/')
            ? $outputDir
            : $this->projectDir . '/' . ltrim($outputDir, '/');
        $absOutputDir = rtrim($absOutputDir, '/');
        $this->filesystem->mkdir($absOutputDir);

        if (is_dir($path)) {
            return $this->processDirectory($io, $path, $absOutputDir, $force, $preview ?? false);
        }

        return $this->processOne($io, $path, $absOutputDir, $force, $preview ?? true, summary: true);
    }

    private function processDirectory(SymfonyStyle $io, string $sourceDir, string $absOutputDir, bool $force, bool $preview): int
    {
        $finder = (new Finder())
            ->files()
            ->in($sourceDir)
            ->name('*.docx')
            ->notName('~$*')
            ->sortByName();

        $total = iterator_count($finder->getIterator());
        if ($total === 0) {
            $io->warning(sprintf('No .docx files under %s', $sourceDir));
            return Command::SUCCESS;
        }

        $io->title(sprintf('Bulk split: %d .docx files → %s', $total, $absOutputDir));

        $progress = $io->createProgressBar($total);
        $progress->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        $progress->start();

        $totalSongs = 0;
        $totalErrors = 0;
        $errors = [];

        foreach ($finder as $file) {
            $progress->setMessage($file->getRelativePathname());
            try {
                $written = $this->processOne(
                    $io,
                    $file->getPathname(),
                    $absOutputDir,
                    $force,
                    preview: false,
                    summary: false,
                );
                $totalSongs += $written;
            } catch (\Throwable $e) {
                $totalErrors++;
                $errors[] = [$file->getRelativePathname(), $e->getMessage()];
            }
            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if ($errors !== []) {
            $io->section(sprintf('%d files errored', count($errors)));
            $io->table(['file', 'error'], $errors);

            $errorLog = $absOutputDir . '/.split-errors.json';
            $this->filesystem->dumpFile(
                $errorLog,
                json_encode([
                    'sourceDir' => $sourceDir,
                    'ranAt'     => date('c'),
                    'errors'    => array_map(
                        static fn(array $row): array => ['file' => $row[0], 'error' => $row[1]],
                        $errors,
                    ),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
            $io->note(sprintf('Error log saved to %s', $errorLog));
        }

        $io->success(sprintf(
            'Bulk split: %d files → %d songs written, %d errors',
            $total,
            $totalSongs,
            $totalErrors,
        ));

        if ($preview) {
            $io->note('Preview suppressed in directory mode. Use -v to dump every .cho, -vv for the parse trace.');
        }

        // SUCCESS as long as we wrote anything; errors are surfaced in the table + log,
        // not via a non-zero exit code (so castor pipelines continue past this stage).
        return $totalSongs > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return int  Number of .cho files written for this docx.
     */
    private function processOne(SymfonyStyle $io, string $path, string $absOutputDir, bool $force, bool $preview, bool $summary): int
    {
        try {
            $reader = IOFactory::createReader('Word2007');
            $phpWord = $reader->load($path);
        } catch (\Throwable $e) {
            if ($summary) {
                $io->error(sprintf('Failed to load docx: %s', $e->getMessage()));
            }
            throw $e;
        }

        $context = $this->parseFilenameContext(basename($path));

        /** @var list<DocxParagraph> $paragraphs */
        $paragraphs = [];
        foreach ($phpWord->getSections() as $section) {
            $this->collectParagraphs($section, $paragraphs);
        }

        $trace = $io->isVeryVerbose() ? [] : null;
        $songs = $this->splitSongs($paragraphs, $context, $trace);

        if ($songs === []) {
            if ($summary) {
                $io->warning(sprintf('No songs detected (no Title* paragraphs): %s', basename($path)));
            }
            return 0;
        }

        if ($summary) {
            $io->title(sprintf('%s → %d songs', basename($path), count($songs)));
            if ($context !== []) {
                $io->writeln(sprintf('Filename context: %s', json_encode($context, JSON_UNESCAPED_UNICODE)));
            }
        }

        $written = [];
        foreach ($songs as $song) {
            $slug = $this->slugify($song->title);

            // Long "titles" are almost always program / credit blocks the docx author tagged with
            // a Title style — not real songs. Skip rather than blowing up on filesystem name limits.
            if (mb_strlen($slug) > 100) {
                if ($summary) {
                    $io->note(sprintf(
                        'Skipping (likely program/credits, title %d chars): %s…',
                        mb_strlen($song->title),
                        mb_substr($song->title, 0, 60),
                    ));
                }
                continue;
            }

            $songDir = sprintf('%s/%s', $absOutputDir, $slug);
            $target = sprintf('%s/%s-lyrics.cho', $songDir, $slug);
            if (file_exists($target) && !$force) {
                if ($summary) {
                    $io->note(sprintf('Skipping (exists): %s', $target));
                }
                continue;
            }
            $cho = $this->renderChordPro($song);
            $this->filesystem->dumpFile($target, $cho);
            $written[] = [
                'title' => $song->title,
                'lines' => substr_count($cho, "\n"),
                'bytes' => strlen($cho),
                'path'  => $target,
            ];
        }

        if ($summary && $written !== []) {
            $io->table(
                ['title', 'lines', 'bytes', 'path'],
                array_map(static fn(array $r): array => [
                    $r['title'],
                    $r['lines'],
                    $r['bytes'],
                    str_replace($absOutputDir . '/', '', $r['path']),
                ], $written),
            );
        }

        if ($io->isVeryVerbose() && $trace !== null) {
            $this->renderTrace($io, $trace);
        }

        if ($io->isVerbose() && $written !== []) {
            foreach ($written as $row) {
                $io->section(sprintf('%s — %s', $row['title'], basename($row['path'])));
                $io->writeln(file_get_contents($row['path']));
            }
        } elseif ($preview && $written !== []) {
            $io->section(sprintf('Preview: %s', $written[0]['title']));
            $io->writeln(file_get_contents($written[0]['path']));
        }

        if ($summary) {
            $io->success(sprintf('Wrote %d .cho files to %s', count($written), $absOutputDir));
        }

        return count($written);
    }

    /**
     * @param list<DocxParagraph> $paragraphs
     * @param array<string, string|int> $context
     * @param null|list<ParseTrace> $trace
     * @return list<ExtractedSong>
     */
    private function splitSongs(array $paragraphs, array $context, ?array &$trace = null): array
    {
        /** @var list<ExtractedSong> $songs */
        $songs = [];
        $current = null;
        $songIdx = -1;

        foreach ($paragraphs as $p) {
            $category = $this->styleCategory($p->paraStyle ?? '', $p->kind);
            $text = trim($p->text);
            $action = '';

            if ($category === SongPart::Title) {
                if ($current !== null) {
                    $songs[] = $current;
                }
                $songIdx++;
                $current = new ExtractedSong(
                    title: $text !== '' ? $text : 'Untitled',
                    context: $context,
                );
                $action = 'NEW SONG';
            } elseif ($current === null) {
                $action = 'skip (no song open)';
            } elseif ($category === SongPart::Caption) {
                if ($text !== '') {
                    $current->subtitle = $text;
                    $action = 'subtitle';
                } else {
                    $action = 'caption (empty)';
                }
            } elseif ($category === SongPart::Blank || $category === SongPart::PageBreak) {
                $current->appendLine($category, '');
                $action = $category->value;
            } elseif ($text === '') {
                $current->appendLine(SongPart::Blank, '');
                $action = 'empty → blank';
            } else {
                $current->appendLine($category, $text);
                $action = $category->value;
            }

            if ($trace !== null) {
                $trace[] = new ParseTrace(
                    paragraphIndex: $p->index,
                    kind: $p->kind,
                    paraStyle: $p->paraStyle,
                    category: $category,
                    songIndex: $current === null ? null : $songIdx,
                    action: $action,
                    text: $text,
                );
            }
        }

        if ($current !== null) {
            $songs[] = $current;
        }

        return $songs;
    }

    private function styleCategory(string $style, string $kind): SongPart
    {
        if ($kind === 'PageBreak') {
            return SongPart::PageBreak;
        }
        if ($kind === 'TextBreak') {
            return SongPart::Blank;
        }
        if ($style === '') {
            return SongPart::Plain;
        }
        $lower = strtolower($style);
        return match (true) {
            str_starts_with($lower, 'title')   => SongPart::Title,
            str_starts_with($lower, 'caption') => SongPart::Caption,
            str_starts_with($lower, 'byline')  => SongPart::Caption,
            str_starts_with($lower, 'chorus')  => SongPart::Chorus,
            str_starts_with($lower, 'bridge')  => SongPart::Bridge,
            str_starts_with($lower, 'verse')   => SongPart::Verse,
            default => SongPart::Plain,
        };
    }

    private function renderChordPro(ExtractedSong $song): string
    {
        $lines = [];
        $lines[] = sprintf('{title: %s}', $song->title);
        if ($song->subtitle !== null) {
            $lines[] = sprintf('{subtitle: %s}', $song->subtitle);
        }
        foreach ($song->context as $key => $value) {
            $lines[] = sprintf('{%s: %s}', $key, $value);
        }
        $lines[] = '';

        $currentBlock = null;
        $bodyLines = [];

        foreach ($song->body as $line) {
            $kind = $line->kind;

            if ($kind === SongPart::Blank || $kind === SongPart::PageBreak) {
                if ($currentBlock !== null) {
                    $bodyLines[] = $currentBlock->chordProClose();
                    $currentBlock = null;
                }
                $bodyLines[] = '';
                continue;
            }

            $blockKind = $kind->isBlock() ? $kind : null;

            if ($blockKind !== $currentBlock) {
                if ($currentBlock !== null) {
                    $bodyLines[] = $currentBlock->chordProClose();
                }
                if ($blockKind !== null) {
                    $bodyLines[] = $blockKind->chordProOpen();
                }
                $currentBlock = $blockKind;
            }

            $bodyLines[] = $line->text;
        }

        if ($currentBlock !== null) {
            $bodyLines[] = $currentBlock->chordProClose();
        }

        $bodyLines = $this->collapseBlankRuns($bodyLines);

        return implode("\n", array_merge($lines, $bodyLines)) . "\n";
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function collapseBlankRuns(array $lines): array
    {
        $out = [];
        $blank = 0;
        foreach ($lines as $line) {
            if ($line === '') {
                $blank++;
                if ($blank > 1) {
                    continue;
                }
            } else {
                $blank = 0;
            }
            $out[] = $line;
        }
        while ($out !== [] && end($out) === '') {
            array_pop($out);
        }
        return $out;
    }

    /**
     * @param list<DocxParagraph> $rows
     */
    private function collectParagraphs(AbstractContainer $container, array &$rows): void
    {
        foreach ($container->getElements() as $el) {
            if ($el instanceof TextRun) {
                $rows[] = new DocxParagraph(
                    index: count($rows),
                    kind: 'TextRun',
                    paraStyle: $this->paraStyleName($el->getParagraphStyle()),
                    text: $this->extractRunText($el),
                );
                continue;
            }
            if ($el instanceof Text) {
                $rows[] = new DocxParagraph(
                    index: count($rows),
                    kind: 'Text',
                    paraStyle: $this->paraStyleName($el->getParagraphStyle()),
                    text: (string) $el->getText(),
                );
                continue;
            }
            if ($el instanceof TextBreak) {
                $rows[] = new DocxParagraph(
                    index: count($rows),
                    kind: 'TextBreak',
                    paraStyle: null,
                    text: '',
                );
                continue;
            }
            if ($el instanceof PageBreak) {
                $rows[] = new DocxParagraph(
                    index: count($rows),
                    kind: 'PageBreak',
                    paraStyle: null,
                    text: '',
                );
                continue;
            }
            if ($el instanceof AbstractContainer) {
                $this->collectParagraphs($el, $rows);
            }
        }
    }

    private function extractRunText(TextRun $run): string
    {
        $text = '';
        foreach ($run->getElements() as $child) {
            if ($child instanceof Text) {
                $text .= (string) $child->getText();
            } elseif ($child instanceof TextBreak) {
                $text .= "\n";
            } elseif (method_exists($child, 'getText')) {
                $text .= (string) $child->getText();
            }
        }
        return $text;
    }

    private function paraStyleName(mixed $style): ?string
    {
        if (is_string($style)) {
            return $style;
        }
        if ($style instanceof Paragraph && method_exists($style, 'getStyleName')) {
            return $style->getStyleName();
        }
        return null;
    }

    /**
     * Build a clean slug from a song title.
     * Apostrophes (both ASCII and smart quotes) are stripped so contractions
     * collapse: "Don't" → "dont", "We've" → "weve", "I'm" → "im".
     */
    private function slugify(string $title): string
    {
        // Decode HTML entities ("Tulips &amp; Roses") and transliterate smart punctuation.
        $clean = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $clean = (string) transliterator_transliterate('Any-Latin; Latin-ASCII;', $clean);
        }
        // Strip apostrophes BEFORE slugging so contractions don't introduce -t-/-s-/-m-.
        $clean = (string) preg_replace("/'+/", '', $clean);

        return mb_strtolower((string) $this->slugger->slug($clean));
    }

    /**
     * @param list<ParseTrace> $trace
     */
    private function renderTrace(SymfonyStyle $io, array $trace): void
    {
        $io->section('Parsing trace');
        $tableRows = [];
        foreach ($trace as $row) {
            $text = $row->text;
            if (mb_strlen($text) > 60) {
                $text = mb_substr($text, 0, 60) . '…';
            }
            $tableRows[] = [
                $row->paragraphIndex,
                $row->songIndex === null ? '-' : (string) $row->songIndex,
                $row->kind,
                $row->paraStyle ?? '-',
                $row->category->value,
                $row->action,
                $text,
            ];
        }
        $io->table(
            ['#', 'song', 'kind', 'paraStyle', 'category', 'action', 'text'],
            $tableRows,
        );
    }

    /**
     * Heuristic: filename like "BurnleyMoran2012Lyric.docx" → {school: "Burnley Moran", year: 2012}
     *
     * @return array<string, string|int>
     */
    private function parseFilenameContext(string $filename): array
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $context = [];

        if (preg_match('/(20\d{2}|19\d{2})/', $stem, $m)) {
            $context['year'] = (int) $m[1];
            $stem = trim(str_replace($m[0], ' ', $stem));
        }

        $stem = preg_replace('/\b(lyric|lyrics|songs|song)\b/i', '', $stem) ?? $stem;
        $stem = preg_replace('/[_-]+/', ' ', $stem) ?? $stem;
        $stem = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $stem) ?? $stem;
        $school = trim(preg_replace('/\s+/', ' ', $stem) ?? $stem);

        if ($school !== '') {
            $context['school'] = $school;
        }

        return $context;
    }
}
