<?php

declare(strict_types=1);

namespace App\Service;

use ChordPro\Line\Lyrics as ChordProLyrics;
use ChordPro\Line\Metadata;
use ChordPro\Parser as ChordProParser;

/**
 * Read-only utilities for inspecting a ChordPro source string.
 * Pure static — no DI, no state, safe to call from property hooks.
 */
final class ChordProInspector
{
    /**
     * Return the text of the FIRST `{start_of_chorus}` … `{end_of_chorus}`
     * block. Repeated chorus blocks are usually identical, so the first
     * one is the canonical hook for grid / preview display.
     *
     * Returns null when the song has no chorus block (some songs are
     * verse-only — see `project_problematic_songs_list` memory).
     */
    public static function firstChorus(?string $cho): ?string
    {
        if ($cho === null || trim($cho) === '') {
            return null;
        }

        try {
            $song = (new ChordProParser())->parse($cho);
        } catch (\Throwable) {
            return null;
        }

        $inChorus = false;
        $lines = [];

        foreach ($song->getLines() as $line) {
            if ($line instanceof Metadata) {
                $name = strtolower($line->getName());
                if ($name === 'start_of_chorus') {
                    $inChorus = true;
                    continue;
                }
                if ($name === 'end_of_chorus') {
                    if ($lines !== []) {
                        return implode("\n", $lines);
                    }
                    $inChorus = false;
                    continue;
                }
                continue;
            }

            if (!$inChorus) {
                continue;
            }

            if ($line instanceof ChordProLyrics) {
                $text = '';
                foreach ($line->getBlocks() as $block) {
                    if (method_exists($block, 'getText')) {
                        $text .= (string) $block->getText();
                    }
                }
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        return $lines !== [] ? implode("\n", $lines) : null;
    }
}
