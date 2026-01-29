<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SongRepository;
use App\Workflow\SongWFDefinition;
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
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Workflow\Marking;
use Zenstruck\Metadata;

use Doctrine\ORM\Mapping\Column;

use Doctrine\DBAL\Types\Types;
#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\UniqueConstraint('song_code', ['code'])]
#[ApiResource(operations: [
    new Get(),
    new GetCollection(name: 'doctrine_songs')],
    parameters: [
        'q' => new QueryParameter(
            filter: new FreeTextQueryFilter(new PartialSearchFilter()),
            properties: ['title'],
        ),
    ],
    normalizationContext: ['groups' => ['song.read', 'rp']])]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'year', 'lyricsLength', 'publisher', 'writers'])]
//#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['school', 'writersArray', 'publishersArray', 'year'], arguments: ["searchParameterName" => "facet_filter"])]
#[Groups(['song.read'])]
#[Assert\EnableAutoMapping]
#[Metadata('translatable', ['title'])]
#[MeiliIndex(
    ui: ['icon' => 'Song'],
    searchable: ['lyrics','title'],
    filterable: ['writersArray', 'publishersArray', 'year'],
    embedders: ['small', 'best']
)]
class Song implements RouteParametersInterface, \Stringable, BabelHooksInterface, MarkingInterface
{
//    use SongTranslationsTrait;
//    use TranslatableHooksTrait;
    use RouteParametersTrait;
    use BabelHooksTrait;
    use MarkingTrait;

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

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $aliases = null;

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
        $this->marking = SongWFDefinition::PLACE_NEW;
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
            $video->song = $this;
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->song === $this) {
                $video->song = null;
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
    // <BABEL:TRANSLATABLE:START title>
    #[Column(type: Types::TEXT, nullable: true)]
    public ?string $title = null;

}
