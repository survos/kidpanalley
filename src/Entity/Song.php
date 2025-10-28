<?php

namespace App\Entity;

use App\Entity\Translations\SongTranslationsTrait;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SongRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Survos\BabelBundle\Attribute\Translatable;
use Survos\BabelBundle\Contract\BabelHooksInterface;
use Survos\BabelBundle\Entity\Traits\BabelHooksTrait;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Survos\MeiliBundle\Api\Filter\FacetsFieldSearchFilter;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Zenstruck\Metadata;

use Doctrine\ORM\Mapping\Column;

use Doctrine\DBAL\Types\Types;
#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\UniqueConstraint('song_code', ['code'])]
#[GetCollection(name: self::MEILI_ROUTE, normalizationContext: ['groups' => ['instance.read', 'tree', 'rp']])]
#[ApiResource(operations: [new Get(), new GetCollection(name: 'doctrine_songs')], normalizationContext: ['groups' => ['song.read', 'rp']])]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'year', 'lyricsLength', 'publisher', 'writers'])]
#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['school', 'writersArray', 'publishersArray', 'year'], arguments: ["searchParameterName" => "facet_filter"])]
#[Groups(['song.read'])]
#[Assert\EnableAutoMapping]
#[Metadata('translatable', ['title'])]
#[MeiliIndex(
    searchable: ['lyrics','title'],
    filterable: ['writersArray', 'publishersArray', 'year'],
    embedders: ['small']
)]
class Song implements RouteParametersInterface, \Stringable, BabelHooksInterface
{
//    use SongTranslationsTrait;
//    use TranslatableHooksTrait;
    use RouteParametersTrait;
    use BabelHooksTrait;

    public const array UNIQUE_PARAMETERS = ['songId' => 'id'];
    public const MEILI_ROUTE = 'meili-song';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer')]
    public readonly ?int $id;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read', 'searchable', 'video.read'])]
    public ?string $description = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['song.read', 'searchable'])]
    public ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['song.facet', 'song.read', 'video.read', 'searchable'])]
    #[Facet]
    public ?int $year = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['song.read', 'video.read', 'searchable'])]
    #[Facet]
    public ?string $school = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $lyrics {
        set {
            $this->lyrics = $value;
            $this->lyricsLength = $value ? mb_strlen($value) : null;
        }
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read'])]
    public ?string $featuredArtist = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $recordingCredits = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $musicians = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $writers = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $wordpressPageId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $recording = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read', 'video.read', 'searchable'])]
    public ?string $publisher = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read'])]
    public ?string $notes = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['song.read'])]
    public ?int $lyricsLength = null;

    #[ORM\OneToMany(mappedBy: 'song', targetEntity: Video::class)]
    #[Groups('song.read')]
    public Collection $videos;

    #[ORM\Column(length: 255)]
    public string $code;

    public function __construct(?string $code = null)
    {
        assert($code, "missing code");
        $this->code = $code;
        $this->videos = new ArrayCollection();
    }

    #[Groups(['song.read'])]
    public function getWritersArray(): array
    {
        return array_values(array_filter(array_map('trim', explode('/', (string) $this->writers)), 'strlen'));
    }

    #[Groups(['song.read'])]
    public function getPublishersArray(): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $this->publisher ?? '')), 'strlen'));
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setSong($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getSong() === $this) {
                $video->setSong(null);
            }
        }
        return $this;
    }

    public static function createCode(string $title, ?string $school = null, string|int|null $year = null): string
    {
        $words = explode(" ", $title);
        $code = sprintf('%s-%d-%s', self::initials($school ?? 'no-school'), $year, join('-', array_slice($words, 0, 2)));
        return substr($code, 0, 32);
    }

    public static function initials(?string $name): string
    {
        preg_match_all('#([A-Z]+)#', $name, $capitals);
        if (count($capitals[1]) >= 2) {
            return mb_substr(implode('', $capitals[1]), 0, 2, 'UTF-8');
        }
        return mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
    }

    #[Groups(['song.read'])]
    #[SerializedName('locale')]
    public function getLocale(): string
    {
        return 'en';
    }

    public function __toString(): string
    {
        return $this->title;
    }

    // DEPRECATED METHODS - Mark for removal after loader is updated

    /**
     * @deprecated Use public property $id directly
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @deprecated Use public property $lyrics directly
     */
    public function getLyrics(): ?string
    {
        return $this->lyrics;
    }

    /**
     * @deprecated Use public property $lyrics directly (includes automatic lyricsLength update)
     */
    public function setLyrics(?string $lyrics): self
    {
        $this->lyrics = $lyrics;
        return $this;
    }

    /**
     * @deprecated Use public property $featuredArtist directly
     */
    public function getFeaturedArtist(): ?string
    {
        return $this->featuredArtist;
    }

    /**
     * @deprecated Use public property $featuredArtist directly
     */
    public function setFeaturedArtist(?string $featuredArtist): self
    {
        $this->featuredArtist = $featuredArtist;
        return $this;
    }

    /**
     * @deprecated Use public property $recordingCredits directly
     */
    public function getRecordingCredits(): ?string
    {
        return $this->recordingCredits;
    }

    /**
     * @deprecated Use public property $recordingCredits directly
     */
    public function setRecordingCredits(?string $recordingCredits): self
    {
        $this->recordingCredits = $recordingCredits;
        return $this;
    }

    /**
     * @deprecated Use public property $musicians directly
     */
    public function getMusicians(): ?string
    {
        return $this->musicians;
    }

    /**
     * @deprecated Use public property $musicians directly
     */
    public function setMusicians(?string $musicians): self
    {
        $this->musicians = $musicians;
        return $this;
    }

    /**
     * @deprecated Use public property $writers directly
     */
    public function getWriters(): ?string
    {
        return $this->writers;
    }

    /**
     * @deprecated Use public property $writers directly
     */
    public function setWriters(?string $writers): self
    {
        $this->writers = $writers;
        return $this;
    }

    /**
     * @deprecated Use public property $wordpressPageId directly
     */
    public function getWordpressPageId(): ?int
    {
        return $this->wordpressPageId;
    }

    /**
     * @deprecated Use public property $wordpressPageId directly
     */
    public function setWordpressPageId(?int $wordpressPageId): self
    {
        $this->wordpressPageId = $wordpressPageId;
        return $this;
    }

    /**
     * @deprecated Use public property $recording directly
     */
    public function getRecording(): ?string
    {
        return $this->recording;
    }

    /**
     * @deprecated Use public property $recording directly
     */
    public function setRecording(?string $recording): self
    {
        $this->recording = $recording;
        return $this;
    }

    /**
     * @deprecated Use public property $publisher directly
     */
    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    /**
     * @deprecated Use public property $publisher directly
     */
    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }

    /**
     * @deprecated Use public property $year directly
     */
    public function setYear(?int $year): self
    {
        $this->year = $year;
        return $this;
    }

    /**
     * @deprecated Use public property $notes directly
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * @deprecated Use public property $notes directly
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * @deprecated Use public property $school directly
     */
    public function getSchool(): ?string
    {
        return $this->school;
    }

    /**
     * @deprecated Use public property $date directly
     */
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @deprecated Use public property $date directly
     */
    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @deprecated Use public property $lyricsLength directly
     */
    public function getLyricsLength(): ?int
    {
        return $this->lyricsLength;
    }

    /**
     * @deprecated Use public property $lyricsLength directly (automatically updated when lyrics are set)
     */
    public function setLyricsLength(?int $lyricsLength): self
    {
        $this->lyricsLength = $lyricsLength;
        return $this;
    }

    /**
     * @deprecated Use public property $videos directly
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }



    // <BABEL:TRANSLATABLE:START title>
    #[Column(type: Types::TEXT, nullable: true)]
    public ?string $title = null;

}
