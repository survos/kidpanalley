<?php

declare(strict_types=1);

namespace App\Command;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:inspect-docx', 'Walk a .docx and surface paragraph-level structural signals (style, alignment, indent, bold/italic) for splitter design')]
final class InspectDocxCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('path to .docx file')]
        string $path,
        #[Option('emit JSON instead of a table')]
        bool $json = false,
        #[Option('cap text preview to N chars (table mode)')]
        int $width = 70,
    ): int {
        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('Not a readable file: %s', $path));
            return Command::FAILURE;
        }

        try {
            $reader = IOFactory::createReader('Word2007');
            $phpWord = $reader->load($path);
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to load docx: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $rows = [];
        $idx = 0;
        foreach ($phpWord->getSections() as $section) {
            $this->walk($section, $rows, $idx);
        }

        if ($json) {
            $io->writeln(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $io->title(sprintf('%s — %d paragraphs', basename($path), count($rows)));

        $tableRows = [];
        foreach ($rows as $row) {
            $text = (string) ($row['text'] ?? '');
            if (mb_strlen($text) > $width) {
                $text = mb_substr($text, 0, $width) . '…';
            }
            $tableRows[] = [
                $row['index'],
                $row['kind'] ?? '-',
                substr((string) ($row['paraStyle'] ?? '-'), 0, 18),
                $row['align'] ?? '-',
                $row['indent'] ?? '-',
                isset($row['boldPct']) ? $row['boldPct'] . '%' : '-',
                isset($row['italicPct']) ? $row['italicPct'] . '%' : '-',
                $row['fontSizesSummary'] ?? '-',
                $text,
            ];
        }

        $io->table(
            ['#', 'kind', 'paraStyle', 'align', 'indent', 'bold', 'italic', 'size', 'text'],
            $tableRows,
        );

        return Command::SUCCESS;
    }

    private function walk(AbstractContainer $container, array &$rows, int &$idx): void
    {
        foreach ($container->getElements() as $el) {
            $this->recordElement($el, $rows, $idx);
        }
    }

    private function recordElement(object $el, array &$rows, int &$idx): void
    {
        if ($el instanceof TextRun) {
            $rows[] = $this->analyzeTextRun($el, $idx++);
            return;
        }

        if ($el instanceof Text) {
            $font = $el->getFontStyle();
            $bold = $this->fontBool($font, 'isBold');
            $italic = $this->fontBool($font, 'isItalic');
            $rows[] = [
                'index' => $idx++,
                'kind' => 'Text',
                'paraStyle' => $this->paraStyleName($el->getParagraphStyle()),
                'align' => $this->paraAlign($el->getParagraphStyle()),
                'indent' => $this->paraIndent($el->getParagraphStyle()),
                'boldPct' => $bold ? 100 : 0,
                'italicPct' => $italic ? 100 : 0,
                'fontSizesSummary' => $this->fontSize($font) ? (string) $this->fontSize($font) : '-',
                'text' => (string) $el->getText(),
            ];
            return;
        }

        if ($el instanceof TextBreak) {
            $rows[] = [
                'index' => $idx++,
                'kind' => 'TextBreak',
                'paraStyle' => $this->paraStyleName($el->getParagraphStyle()),
                'align' => $this->paraAlign($el->getParagraphStyle()),
                'indent' => $this->paraIndent($el->getParagraphStyle()),
                'text' => '[BLANK]',
            ];
            return;
        }

        if ($el instanceof PageBreak) {
            $rows[] = [
                'index' => $idx++,
                'kind' => 'PageBreak',
                'text' => '[PAGE BREAK]',
            ];
            return;
        }

        if ($el instanceof ListItem) {
            $rows[] = [
                'index' => $idx++,
                'kind' => 'ListItem',
                'depth' => method_exists($el, 'getDepth') ? $el->getDepth() : null,
                'text' => method_exists($el, 'getText') ? (string) $el->getText() : '',
            ];
            return;
        }

        if ($el instanceof Table) {
            $rows[] = [
                'index' => $idx++,
                'kind' => 'Table',
                'text' => '[TABLE — descending into cells]',
            ];
            foreach ($el->getRows() as $tr) {
                foreach ($tr->getCells() as $cell) {
                    $this->walk($cell, $rows, $idx);
                }
            }
            return;
        }

        if ($el instanceof AbstractContainer) {
            $this->walk($el, $rows, $idx);
            return;
        }

        $rows[] = [
            'index' => $idx++,
            'kind' => (new \ReflectionClass($el))->getShortName(),
            'text' => '[unhandled]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeTextRun(TextRun $run, int $index): array
    {
        $totalChars = 0;
        $boldChars = 0;
        $italicChars = 0;
        $fontSizes = [];
        $combined = '';

        foreach ($run->getElements() as $child) {
            if ($child instanceof Text) {
                $t = (string) $child->getText();
                $combined .= $t;
                $len = mb_strlen($t);
                $totalChars += $len;

                $f = $child->getFontStyle();
                if ($this->fontBool($f, 'isBold')) {
                    $boldChars += $len;
                }
                if ($this->fontBool($f, 'isItalic')) {
                    $italicChars += $len;
                }
                $size = $this->fontSize($f);
                if ($size !== null) {
                    $fontSizes[$size] = ($fontSizes[$size] ?? 0) + $len;
                }
            } elseif ($child instanceof TextBreak) {
                $combined .= "\n";
            } elseif (method_exists($child, 'getText')) {
                $combined .= (string) $child->getText();
            }
        }

        $sizesSummary = '-';
        if ($fontSizes !== []) {
            $parts = [];
            foreach ($fontSizes as $size => $count) {
                $parts[] = sprintf('%d×%d', $size, $count);
            }
            $sizesSummary = implode(',', $parts);
        }

        return [
            'index' => $index,
            'kind' => 'TextRun',
            'paraStyle' => $this->paraStyleName($run->getParagraphStyle()),
            'align' => $this->paraAlign($run->getParagraphStyle()),
            'indent' => $this->paraIndent($run->getParagraphStyle()),
            'boldPct' => $totalChars > 0 ? (int) round($boldChars * 100 / $totalChars) : 0,
            'italicPct' => $totalChars > 0 ? (int) round($italicChars * 100 / $totalChars) : 0,
            'fontSizes' => $fontSizes,
            'fontSizesSummary' => $sizesSummary,
            'runs' => count($run->getElements()),
            'text' => $combined,
        ];
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

    private function paraAlign(mixed $style): ?string
    {
        if ($style instanceof Paragraph && method_exists($style, 'getAlignment')) {
            return $style->getAlignment() ?: null;
        }
        return null;
    }

    private function paraIndent(mixed $style): ?string
    {
        if (!$style instanceof Paragraph || !method_exists($style, 'getIndentation')) {
            return null;
        }
        $ind = $style->getIndentation();
        if ($ind === null) {
            return null;
        }
        $left = method_exists($ind, 'getLeft') ? $ind->getLeft() : null;
        $first = method_exists($ind, 'getFirstLine') ? $ind->getFirstLine() : null;
        if ($left === null && $first === null) {
            return null;
        }
        if (!$left && !$first) {
            return null;
        }
        return sprintf('L%s/F%s', $left ?? '0', $first ?? '0');
    }

    private function fontBool(mixed $style, string $method): bool
    {
        if ($style instanceof Font && method_exists($style, $method)) {
            return (bool) $style->{$method}();
        }
        return false;
    }

    private function fontSize(mixed $style): ?int
    {
        if ($style instanceof Font && method_exists($style, 'getSize')) {
            $s = $style->getSize();
            return $s ? (int) $s : null;
        }
        return null;
    }
}
