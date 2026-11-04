<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Rules;

use Astrainer\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityRelationRule
{
    public static function check(EntityMetadata $entity, BeforeAnalysisContext $context): void
    {
        foreach ($entity->properties as $property) {
            if (
                !$property->mapped
                || $property->toMany
                || $property->targetEntity === null
                || $property->nativeType === null
                || $property->location === null
            ) {
                continue;
            }

            $relationType = Type::namedObject($property->targetEntity);
            $expectedType = $property->nullable ? Type::union($relationType, Type::null()) : $relationType;
            if ($context->types->isContainedBy($expectedType, $property->nativeType)) {
                continue;
            }

            $context->report(
                Level::Error,
                'entity-relation-type',
                Issue::at(
                    sprintf(
                        'Doctrine relation to %s is incompatible with property type %s::$%s (%s).',
                        $property->targetEntity,
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