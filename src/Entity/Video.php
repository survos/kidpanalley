<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\VideoRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\MeiliBundle\Api\Filter\FacetsFieldSearchFilter;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new Get(),

        new GetCollection(
//            provider: MeiliSearchStateProvider::class,
        )],
    normalizationContext: ['groups' => ['video.read', 'rp']]
)]
#[GetCollection(
    name: self::MEILI_ROUTE,
    uriTemplate: "meili-videos", // was {indexName}
//    uriVariables: ["indexName"],
//    provider: MeiliSearchStateProvider::class,
    normalizationContext: [
        'groups' => ['video.read', 'rp'],
    ]
)]

#[ApiFilter(OrderFilter::class, properties: ['title','year'])]
#[ApiFilter(SearchFilter::class, properties: ['title'=>'partial', 'description' => 'partial'])]
#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[Groups(['video.read'])]

#[ApiFilter(FacetsFieldSearchFilter::class,
    properties: ['school','year'],
    arguments: [ "searchParameterName" => "facet_filter"]
)]
#[MeiliIndex(
    ui: ['icon' => 'Video'],
    filterable: ['school','year'],
)]

class Video implements RouteParametersInterface, \Stringable
{
    use RouteParametersTrait;

    const UNIQUE_PARAMETERS=['videoId' => 'youtubeId'];
    const MEILI_ROUTE='meili-video';
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(type: 'integer')]
    #[Groups('song.read')]
    private $id;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    #[Groups('song.read')]
    #[MeiliId]
    private string $youtubeId;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['video.read'])]
    public string $title;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['video.read'])]
    public ?string $description=null;
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['video.read'])]
    private $date;

    #[ORM\Column(nullable: true)]
    private array $rawData = [];

    #[ORM\ManyToOne(inversedBy: 'videos', targetEntity: Song::class, cascade: ['persist'], fetch: 'EAGER')]
    #[Groups(['video.read'])]
    private ?Song $song = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('song.read')]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups('song.read')]
    public ?array $thumbnails = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['video.read'])]
    private ?int $year = null;
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getYoutubeId(): ?string
    {
        return $this->youtubeId;
    }
    public function setYoutubeId(?string $youtubeId): self
    {
        $this->youtubeId = $youtubeId;

        return $this;
    }
    public function getYoutubeUrl(): string
    {
        return sprintf('https://www.youtube.com/watch?v=' . $this->getYoutubeId());
    }
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }
    public function setDate(null|\DateTimeInterface|\DateTime|string $date): self
    {
        if (is_string($date)) {
            $date = strtotime($date);
        }
        $this->date = $date;
        if ($date->format('Y')) {
            $this->year = (int)$date->format('Y');
        }

        return $this;
    }

    public function getRawData(): array|object|null
    {
        return $this->rawData;
    }

    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    public function __toString()
    {
        return $this->getYoutubeId();
    }

    public function getSong(): ?Song
    {
        return $this->song;
    }

    public function setSong(?Song $song): static
    {
        $this->song = $song;

        return $this;
    }

    #[Groups(['video.read'])]
    public function getSchool(): ?string
    {
        return $this->getSong()?->school;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }
}
