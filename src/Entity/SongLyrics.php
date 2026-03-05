<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One row per song extracted from a lyrics document.
 * A single FileAsset (.doc/.docx) can contain multiple songs,
 * so this is a many-to-many bridge with the extracted lyrics text.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint('song_lyrics_unique', ['song_id', 'file_asset_id'])]
class SongLyrics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Song::class, inversedBy: 'songLyrics')]
        #[ORM\JoinColumn(nullable: false)]
        public Song $song,

        #[ORM\ManyToOne(targetEntity: FileAsset::class)]
        #[ORM\JoinColumn(nullable: false)]
        public FileAsset $fileAsset,

        /** The lyrics text extracted for this specific song from the document. */
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $lyricsText = null,

        /** The matched title string as it appeared in the source document. */
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $sourceTitle = null,
    ) {
    }
}
