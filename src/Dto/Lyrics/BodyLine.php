<?php

declare(strict_types=1);

namespace App\Dto\Lyrics;

/**
 * One line in a song's body, post-categorisation.
 */
final readonly class BodyLine
{
    public function __construct(
        public SongPart $kind,
        public string $text,
    ) {
    }
}
