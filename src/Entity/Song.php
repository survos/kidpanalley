<?php

namespace App\Entity;

use ApiPlatform\Metadata\GetCollection;
use App\Repository\SongRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\State\MeilliSearchStateProvider;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => 'song.read', 'rp']
)]
#[ApiFilter(OrderFilter::class, properties: ['title','year','school'])]
#[ApiFilter(SearchFilter::class, properties: ['title'=>'partial'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['title'])]
#[ORM\Entity(repositoryClass: SongRepository::class)]

#[ApiFilter(FacetsFieldSearchFilter::class, properties: ["school","year"], arguments: [
    "searchParameterName" => "facet_filter",
])]

#[GetCollection(
    normalizationContext: ['movie.read', 'rp', 'searchable'],
    provider: MeilliSearchStateProvider::class # MeilisearchProvider

)]

class Song implements RouteParametersInterface, \Stringable
{
    use RouteParametersTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['song.read'])]
    private $id;
    #[ORM\Column(type: 'text')]
    #[Groups(['song.read', 'searchable'])]
    private $title;
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['song.read', 'searchable'])]
    private $date;
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['song.read', 'searchable'])]
    private $year;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['song.read', 'searchable'])]
    private $school;
    #[ORM\Column(type: 'text', nullable: true)]
    private $lyrics;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read'])]
    private $featuredArtist;
    #[ORM\Column(type: 'text', nullable: true)]
    private $recordingCredits;
    #[ORM\Column(type: 'text', nullable: true)]
    private $musicians;
    #[ORM\Column(type: 'text', nullable: true)]
    private $writers;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $wordpressPageId;
    #[ORM\Column(type: 'text', nullable: true)]
    private $recording;
    #[ORM\Column(type: 'text', nullable: true)]
    private $publisher;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['song.read'])]
    private $notes;
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['song.read'])]
    private $lyricsLength;
    public function getId(): ?int
    {
        return $this->id;
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
    public function getLyrics(): ?string
    {
        return $this->lyrics;
    }
    public function setLyrics(?string $lyrics): self
    {
        $this->lyrics = $lyrics;
        $this->setLyricsLength(mb_strlen($lyrics));

        return $this;
    }
    public function getFeaturedArtist(): ?string
    {
        return $this->featuredArtist;
    }
    public function setFeaturedArtist(?string $featuredArtist): self
    {
        $this->featuredArtist = $featuredArtist;

        return $this;
    }
    public function getRecordingCredits(): ?string
    {
        return $this->recordingCredits;
    }
    public function setRecordingCredits(?string $recordingCredits): self
    {
        $this->recordingCredits = $recordingCredits;

        return $this;
    }
    public function getMusicians(): ?string
    {
        return $this->musicians;
    }
    public function setMusicians(?string $musicians): self
    {
        $this->musicians = $musicians;

        return $this;
    }
    public function getWriters(): ?string
    {
        return $this->writers;
    }
    public function setWriters(?string $writers): self
    {
        $this->writers = $writers;

        return $this;
    }
    public function getWordpressPageId(): ?int
    {
        return $this->wordpressPageId;
    }
    public function setWordpressPageId(?int $wordpressPageId): self
    {
        $this->wordpressPageId = $wordpressPageId;

        return $this;
    }
    public function getRecording(): ?string
    {
        return $this->recording;
    }
    public function setRecording(?string $recording): self
    {
        $this->recording = $recording;

        return $this;
    }
    public function getPublisher(): ?string
    {
        return $this->publisher;
    }
    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }
    public function getYear(): ?int
    {
        return $this->year;
    }
    public function setYear(?int $year): self
    {
        $this->year = $year;

        return $this;
    }
    public function getNotes(): ?string
    {
        return $this->notes;
    }
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }
    public function getSchool(): ?string
    {
        return $this->school;
    }
    public function setSchool(?string $school): self
    {
        $this->school = $school;

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
    public function getLyricsLength(): ?int
    {
        return $this->lyricsLength;
    }
    public function setLyricsLength(?int $lyricsLength): self
    {
        $this->lyricsLength = $lyricsLength;

        return $this;
    }
    #[Groups(['song.read'])]
    public function getUniqueIdentifiers(): array
    {
        return ['id' => $this->getId()];
    }

    public function __toString()
    {
        return $this->getTitle();
    }


}
