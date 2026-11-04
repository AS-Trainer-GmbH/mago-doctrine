<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine;

use Gimmickmedia\Mago\Doctrine\Analyzer\DoctrinePlugin;
use Mago\Sdk\Extension;

final class DoctrineExtension
{
    /**
     * @param list<string> $entityNamespaces
     * @param array<string, \Mago\Sdk\Analyzer\Type> $customTypes DBAL type names to inferred PHP types
     * @param array<string, \Mago\Sdk\Analyzer\Type> $customFunctions DQL function names to inferred return types
     */
    public static function create(
        array $entityNamespaces = [],
        bool $reportFinalEntities = true,
        bool $nativeLazyObjects = false,
        bool $allowNullablePropertyForRequiredField = false,
        string $ormRepositoryClass = 'Doctrine\\ORM\\EntityRepository',
        string $odmRepositoryClass = 'Doctrine\\ODM\\MongoDB\\Repository\\DocumentRepository',
        array $customTypes = [],
        array $customFunctions = [],
        bool $reportUnknownTypes = false,
        bool $reportDynamicQueryBuilders = false,
        bool $allCollectionsSelectable = true,
        ?string $objectManagerLoader = null,
        bool $literalString = false,
    ): Extension {
        $config = new DoctrineConfig(
            entityNamespaces: $entityNamespaces,
            reportFinalEntities: $reportFinalEntities,
            nativeLazyObjects: $nativeLazyObjects,
            allowNullablePropertyForRequiredField: $allowNullablePropertyForRequiredField,
            ormRepositoryClass: $ormRepositoryClass,
            odmRepositoryClass: $odmRepositoryClass,
            customTypes: $customTypes,
            customFunctions: $customFunctions,
            reportUnknownTypes: $reportUnknownTypes,
            reportDynamicQueryBuilders: $reportDynamicQueryBuilders,
            allCollectionsSelectable: $allCollectionsSelectable,
            objectManagerLoader: $objectManagerLoader,
            literalString: $literalString,
        );

        return new Extension(
            identifier: 'gimmickmedia/doctrine',
            name: 'Doctrine ORM support',
            version: '1.0.0',
            analyzerPlugins: [new DoctrinePlugin($config)],
        );
    }
}