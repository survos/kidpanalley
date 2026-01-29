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
    public private(set) ?int $id = null;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    #[Groups('song.read')]
    #[MeiliId]
    public ?string $youtubeId = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['video.read'])]
    public ?string $title = null;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['video.read'])]
    public ?string $description=null;
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['video.read'])]
    public ?\DateTimeInterface $date {
        set {
            if (is_string($value)) {
                $value = new \DateTime($value);
            }
            $this->date = $value;
            $this->year = $value?->format('Y') ? (int)$value->format('Y') : null;
        }
    }

    #[ORM\Column(nullable: true)]
    public array $rawData = [];

    #[ORM\ManyToOne(inversedBy: 'videos', targetEntity: Song::class, cascade: ['persist'], fetch: 'EAGER')]
    #[Groups(['video.read'])]
    public ?Song $song = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('song.read')]
    public ?string $thumbnailUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups('song.read')]
    public ?array $thumbnails = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['video.read'])]
    public ?int $year = null;
    public function getYoutubeUrl(): string
    {
        return sprintf('https://www.youtube.com/watch?v=%s', $this->youtubeId);
    }

    public function __toString()
    {
        return (string)$this->youtubeId;
    }

    #[Groups(['video.read'])]
    public function getSchool(): ?string
    {
        return $this->song?->school;
    }
}
