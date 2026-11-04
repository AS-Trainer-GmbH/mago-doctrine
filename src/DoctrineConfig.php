<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine;

use Mago\Sdk\Analyzer\Type;

final class DoctrineConfig
{
    /**
     * @param list<string> $entityNamespaces
     * @param array<string, Type> $customTypes DBAL type names to inferred PHP types
     * @param array<string, Type> $customFunctions DQL function names to inferred return types
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
    ) {}
}