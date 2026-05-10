<?php

declare(strict_types=1);

namespace App\Dto\Lyrics;

enum SongPart: string
{
    case Title     = 'title';
    case Caption   = 'caption';
    case Verse     = 'verse';
    case Chorus    = 'chorus';
    case Bridge    = 'bridge';
    case Plain     = 'plain';
    case Blank     = 'blank';
    case PageBreak = 'pagebreak';

    public function isBlock(): bool
    {
        return match ($this) {
            self::Verse, self::Chorus, self::Bridge => true,
            default => false,
        };
    }

    public function chordProOpen(): ?string
    {
        return match ($this) {
            self::Verse  => '{start_of_verse}',
            self::Chorus => '{start_of_chorus}',
            self::Bridge => '{start_of_bridge}',
            default => null,
        };
    }

    public function chordProClose(): ?string
    {
        return match ($this) {
            self::Verse  => '{end_of_verse}',
            self::Chorus => '{end_of_chorus}',
            self::Bridge => '{end_of_bridge}',
            default => null,
        };
    }
}
