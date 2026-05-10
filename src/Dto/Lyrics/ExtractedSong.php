<?php

declare(strict_types=1);

namespace App\Dto\Lyrics;

/**
 * One song extracted from a .docx file.
 * Title and context are immutable; subtitle and body are accumulated during parsing.
 */
final class ExtractedSong
{
    public ?string $subtitle = null;

    /** @var list<BodyLine> */
    public array $body = [];

    /**
     * @param array<string, string|int> $context  filename-derived directives (school, year, …)
     */
    public function __construct(
        public readonly string $title,
        public readonly array $context = [],
    ) {
    }

    public function appendLine(SongPart $kind, string $text): void
    {
        $this->body[] = new BodyLine($kind, $text);
    }
}
