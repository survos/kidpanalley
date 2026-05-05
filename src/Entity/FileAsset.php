<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Filter\FacetsFieldSearchFilter;
use App\Repository\FileAssetRepository;
use App\Workflow\FileAssetWFDefinition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\ApiGridBundle\Api\Filter\MultiFieldSearchFilter;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;

#[ORM\Entity(repositoryClass: FileAssetRepository::class)]
#[ORM\UniqueConstraint(name: 'file_asset_path', columns: ['path'])]
#[ApiResource(operations: [
    new Get(),
    new GetCollection(name: self::DOCTRINE_ROUTE),
])]
#[ApiFilter(SearchFilter::class, properties: [
    'filename' => 'partial',
    'relativePath' => 'partial',
    'path' => 'partial',
    'dirname' => 'partial',
    'extension' => 'partial',
    'mimeType' => 'partial',
    'type' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: ['filename', 'size', 'modifiedTime', 'duration', 'extension', 'mimeType', 'type'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['filename', 'relativePath', 'dirname'])]
#[ApiFilter(FacetsFieldSearchFilter::class,
    properties: ['type'],
    arguments: ["searchParameterName" => "facet_filter"]
)]
#[EntityMeta(
    icon: 'tabler:files',
    order: 30,
    group: 'Catalog',
    label: 'Files',
    description: 'Source documents, charts, audio, video, and imported file metadata.'
)]
#[MeiliIndex(
    ui: ['icon' => 'FileAsset'],
    filterable: ['type'],
)]
class FileAsset implements MarkingInterface
{
    use MarkingTrait;

    public const DOCTRINE_ROUTE = 'api_file_assets';

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
        #[ORM\Column(length: 255, nullable: true)]
        public ?string $school = null,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $year = null,
        #[ORM\Column(length: 127, nullable: true)]
        public ?string $mimeType = null,
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
