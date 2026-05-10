<?php

declare(strict_types=1);

namespace App\Dto\Lyrics;

/**
 * One paragraph harvested from a .docx file via PHPWord.
 * Pre-categorisation: holds the raw signals (kind + paraStyle name).
 */
final readonly class DocxParagraph
{
    public function __construct(
        public int $index,
        public string $kind,        // TextRun | Text | TextBreak | PageBreak
        public ?string $paraStyle,
        public string $text,
    ) {
    }
}
