<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Metadata;

use Mago\Sdk\SourceLocation;

/**
 * @phpstan-type EntityProperties array<string, DoctrinePropertyMetadata>
 */
final class EntityMetadata
{
    /**
     * @param array<string, DoctrinePropertyMetadata> $properties
     */
    public function __construct(
        public readonly string $className,
        public readonly string $repositoryClass,
        public readonly array $properties,
        public readonly bool $odm,
        public readonly bool $readOnly,
        public readonly bool $final,
        public readonly ?SourceLocation $location,
        public readonly ?SourceLocation $nameLocation,
    ) {}

    public function getProperty(string $name): ?DoctrinePropertyMetadata
    {
        return $this->properties[ltrim($name, '$')] ?? null;
    }
}