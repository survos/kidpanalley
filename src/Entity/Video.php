<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\VideoRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => 'video.read', 'rp']
)]
#[ApiFilter(OrderFilter::class, properties: ['title'])]
#[ApiFilter(SearchFilter::class, properties: ['title'=>'partial'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['title', 'description'])]
#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[Groups(['video.read'])]
class Video implements RouteParametersInterface, \Stringable
{
    use RouteParametersTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('song.read')]
    private $id;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    #[Groups('song.read')]
    private $youtubeId;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $title;
    #[ORM\Column(type: 'text', nullable: true)]
    private $description;
    #[ORM\Column(type: 'date', nullable: true)]
    private $date;

    #[ORM\Column(nullable: true)]
    private array $rawData = [];

    #[ORM\ManyToOne(inversedBy: 'videos', targetEntity: Song::class)]
    private ?Song $song = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('song.read')]
    private ?string $thumbnailUrl = null;
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
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }
    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
    #[Groups(['rp','video.read'])]
    public function getUniqueIdentifiers(): array
    {
        return ['videoId' => $this->getYoutubeId()];
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

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }
}
