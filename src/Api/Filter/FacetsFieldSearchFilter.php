<?php

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class FacetsFieldSearchFilter extends AbstractFilter implements FilterInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private string $searchParameterName = 'facet_filter',
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if ($value === null || $property !== $this->searchParameterName) {
            return;
        }

        $allowed = array_keys($this->getProperties() ?? []);

        foreach ((array)$value as $filter) {
            $parts = explode(',', (string)$filter, 3);
            if (count($parts) < 3) {
                continue;
            }

            [$field, , $rawValues] = $parts;
            if (!in_array($field, $allowed, true)) {
                continue;
            }

            $values = strlen($rawValues) ? array_values(array_filter(explode('|', $rawValues), static fn(string $v): bool => $v !== '')) : [null];
            $this->addWhereIn($queryBuilder, $queryNameGenerator, $field, $values);
        }
    }

    private function addWhereIn(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $field,
        array $values,
    ): void {
        $alias = $queryBuilder->getRootAliases()[0];

        if ($values === [null]) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNull(sprintf('%s.%s', $alias, $field)));

            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName($field);
        $queryBuilder
            ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $parameterName))
            ->setParameter($parameterName, $values);
    }

    public function getDescription(string $resourceClass): array
    {
        $props = $this->getProperties();
        if ($props === null) {
            throw new \InvalidArgumentException('Properties must be specified');
        }

        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type' => 'string',
                'is_collection' => true,
                'required' => false,
                'openapi' => [
                    'description' => 'Filter by facet selections',
                ],
            ],
        ];
    }
}
