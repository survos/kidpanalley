<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\BaseBundle\Entity\SurvosBaseEntity;

#[ORM\Entity(repositoryClass: \App\Repository\VideoRepository::class)]
class Video extends SurvosBaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private $youtubeId;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $title;
    #[ORM\Column(type: 'text', nullable: true)]
    private $description;
    #[ORM\Column(type: 'date', nullable: true)]
    private $date;
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
    public function getUniqueIdentifiers(): array
    {
        return ['videoId' => $this->getYoutubeId()];
    }
}
