<?php

declare(strict_types=1);

namespace App\Dto\Lyrics;

/**
 * One row of the splitter's per-paragraph parse trace (rendered with -vv).
 */
final readonly class ParseTrace
{
    public function __construct(
        public int $paragraphIndex,
        public string $kind,
        public ?string $paraStyle,
        public SongPart $category,
        public ?int $songIndex,
        public string $action,
        public string $text,
    ) {
    }
}
