<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Rules;

use Astrainer\Mago\Doctrine\Analyzer\DoctrineTypeMapper;
use Astrainer\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityColumnRule
{
    public static function check(EntityMetadata $entity, BeforeAnalysisContext $context): void
    {
        foreach ($entity->properties as $property) {
            if (!$property->mapped || $property->columnType === null || $property->nativeType === null || $property->location === null) {
                continue;
            }

            $mappedType = DoctrineTypeMapper::toPhpType($property->columnType, $property->enumType);
            if ($mappedType === null) {
                continue;
            }

            $expectedType = DoctrineTypeMapper::nullable($mappedType, $property->nullable);
            if ($context->types->isContainedBy($expectedType, $property->nativeType)) {
                continue;
            }

            $context->report(
                Level::Error,
                'entity-column-type',
                Issue::at(
                    sprintf(
                        'Doctrine column type %s is incompatible with property type %s::$%s (%s).',
                        $property->columnType,
                        $entity->className,
                        $property->name,
                        $property->nativeType,
                    ),
                    $property->location,
                ),
            );
        }
    }
}