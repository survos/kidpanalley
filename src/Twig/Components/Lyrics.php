<?php

declare(strict_types=1);

namespace App\Twig\Components;

use ChordPro\Line\EmptyLine;
use ChordPro\Line\Lyrics as ChordProLyrics;
use ChordPro\Line\Metadata;
use ChordPro\Parser as ChordProParser;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Renders ChordPro lyrics with the songbook indentation convention used in
 * the source .docx files: chorus blocks are outdented (sit at the left
 * margin) while verses are indented. Bridge falls between the two.
 *
 * Usage:
 *   <twig:Lyrics text="{{ song.lyrics }}" />
 */
#[AsTwigComponent]
final class Lyrics
{
    public ?string $text = null;

    /**
     * Block type names understood by the renderer (lower-case prefix of the
     * `start_of_*` / `end_of_*` ChordPro directive). Anything else is
     * rendered as 'plain'.
     *
     * @return list<array{type: string, lines: list<string>}>
     */
    public function getSegments(): array
    {
        if ($this->text === null || trim($this->text) === '') {
            return [];
        }

        try {
            $song = (new ChordProParser())->parse($this->text);
        } catch (\Throwable) {
            return [['type' => 'fallback', 'lines' => [$this->text]]];
        }

        /** @var list<array{type: string, lines: list<string>}> $segments */
        $segments = [];
        $currentType = 'plain';
        $currentLines = [];

        $flush = static function () use (&$segments, &$currentType, &$currentLines): void {
            if ($currentLines !== []) {
                $segments[] = ['type' => $currentType, 'lines' => $currentLines];
                $currentLines = [];
            }
        };

        foreach ($song->getLines() as $line) {
            if ($line instanceof Metadata) {
                $name = strtolower($line->getName());
                if (str_starts_with($name, 'start_of_')) {
                    $flush();
                    $currentType = substr($name, 9); // verse | chorus | bridge
                    continue;
                }
                if (str_starts_with($name, 'end_of_')) {
                    $flush();
                    $currentType = 'plain';
                    continue;
                }
                // header directives (title/school/year/...) are shown
                // separately by the page; skip in body
                continue;
            }

            if ($line instanceof EmptyLine) {
                // blank between blocks → segment break; blank inside a
                // block → preserved as empty line
                if ($currentLines !== []) {
                    $currentLines[] = '';
                }
                continue;
            }

            if ($line instanceof ChordProLyrics) {
                $text = '';
                foreach ($line->getBlocks() as $block) {
                    if (method_exists($block, 'getText')) {
                        $text .= (string) $block->getText();
                    }
                }
                $currentLines[] = $text;
            }
        }

        $flush();

        // Trim trailing blank lines inside each segment for tidy output
        foreach ($segments as &$segment) {
            while ($segment['lines'] !== [] && end($segment['lines']) === '') {
                array_pop($segment['lines']);
            }
        }
        unset($segment);

        return array_values(array_filter($segments, static fn(array $s): bool => $s['lines'] !== []));
    }
}
