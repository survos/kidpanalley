<?php

namespace App\Entity;

use App\Workflow\FileAssetWFDefinition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\MeiliBundle\Api\Filter\FacetsFieldSearchFilter;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use ApiPlatform\Metadata\ApiFilter;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'file_asset_path', columns: ['path'])]
#[ApiFilter(FacetsFieldSearchFilter::class,
    properties: ['type', 'extension', 'dirname', 'isReadable'],
    arguments: ["searchParameterName" => "facet_filter"]
)]
#[MeiliIndex(
    ui: ['icon' => 'FileAsset'],
    filterable: ['type', 'extension', 'dirname', 'isReadable'],
)]
class FileAsset implements MarkingInterface
{
    use MarkingTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'text')]
        public string $path,
        #[ORM\Column(length: 512)]
        public string $relativePath,
        #[ORM\Column(length: 255)]
        public string $filename,
        #[ORM\Column(length: 16)]
        public string $extension,
        #[ORM\Column(length: 512, nullable: true)]
        public ?string $dirname = null,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $size = null,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $modifiedTime = null,
        #[ORM\Column(type: 'boolean')]
        public bool $isReadable = true,
        #[ORM\Column(length: 32)]
        public string $type = 'other',
        #[ORM\Column(type: Types::FLOAT, nullable: true)]
        public ?float $duration = null,
        #[ORM\Column(type: Types::JSON, options: ['jsonb' => true], nullable: true)]
        public ?array $probedData = null,
        #[ORM\Column(type: 'json', nullable: true)]
        public ?array $lyricsCandidates = null,
    ) {
        $this->marking = FileAssetWFDefinition::PLACE_NEW;

    }
}
