<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Metadata;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\SourceLocation;

final class DoctrinePropertyMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly bool $mapped,
        public readonly ?string $columnType,
        public readonly ?string $enumType,
        public readonly bool $nullable,
        public readonly ?string $targetEntity,
        public readonly bool $toMany,
        public readonly bool $identifier,
        public readonly bool $generated,
        public readonly bool $version,
        public readonly bool $frameworkManaged,
        public readonly ?Type $nativeType,
        public readonly ?SourceLocation $location,
    ) {}
}