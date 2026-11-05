<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine;

use Mago\Sdk\Analyzer\Type;

final class DoctrineConfig
{
    /**
     * @param list<string> $entityNamespaces
     * @param array<string, Type|string> $customTypes DBAL type names to inferred PHP types or Doctrine type classes
     * @param array<string, class-string> $types DBAL type names to Doctrine type classes
     * @param array<string, Type> $customFunctions DQL function names to inferred return types
     * @param ?list<string> $symfonyConfigPaths Symfony Doctrine YAML config paths; null uses config/packages/doctrine.yaml
     */
    public function __construct(
        public readonly array $entityNamespaces = [],
        public readonly bool $reportFinalEntities = true,
        public readonly bool $nativeLazyObjects = false,
        public readonly bool $allowNullablePropertyForRequiredField = false,
        public readonly string $ormRepositoryClass = 'Doctrine\\ORM\\EntityRepository',
        public readonly string $odmRepositoryClass = 'Doctrine\\ODM\\MongoDB\\Repository\\DocumentRepository',
        public readonly array $customTypes = [],
        public readonly array $customFunctions = [],
        public readonly bool $reportUnknownTypes = false,
        public readonly bool $reportDynamicQueryBuilders = false,
        public readonly bool $allCollectionsSelectable = true,
        public readonly ?string $objectManagerLoader = null,
        public readonly bool $literalString = false,
        public readonly array $types = [],
        public readonly ?array $symfonyConfigPaths = null,
    ) {}
}