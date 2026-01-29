<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Survos\MeiliBundle\Api\Filter\FacetsFieldSearchFilter;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use ApiPlatform\Metadata\ApiFilter;

#[ORM\Entity]
#[ApiFilter(FacetsFieldSearchFilter::class,
    properties: ['format', 'variant'],
    arguments: ["searchParameterName" => "facet_filter"]
)]
#[MeiliIndex(
    ui: ['icon' => 'Audio'],
    filterable: ['format', 'variant'],
)]
class Audio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\OneToOne(targetEntity: FileAsset::class, cascade: ['persist'])]
        #[ORM\JoinColumn(nullable: false, unique: true)]
        public FileAsset $fileAsset,
        #[ORM\ManyToOne(targetEntity: Song::class)]
        #[ORM\JoinColumn(nullable: false)]
        public Song $song,
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(length: 16)]
        public string $format,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $size = null,
        #[ORM\Column(length: 32, nullable: true)]
        public ?string $variant = null,
    ) {
    }
}
