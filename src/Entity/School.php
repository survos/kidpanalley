<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\SchoolRepository::class)]
class School
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public private(set) ?int $id = null;
    #[ORM\Column(type: 'string', length: 255)]
    public string $name;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    public ?string $city = null;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    public ?string $state = null;
}
