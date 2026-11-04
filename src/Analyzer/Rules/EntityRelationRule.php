<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
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
                || $property->targetEntity === null
                || $property->columnType !== null
                || $property->nativeType === null
                || $property->location === null
            ) {
                continue;
            }

            if ($property->toMany) {
                $expectedType = Type::namedObject('Doctrine\\Common\\Collections\\Collection');
            } else {
                $relationType = Type::namedObject($property->targetEntity);
                $expectedType = $property->nullable ? Type::union($relationType, Type::null()) : $relationType;
            }

            if ($context->types->isContainedBy($expectedType, $property->nativeType)) {
                continue;
            }

            $context->report(
                Level::Error,
                'entity-relation-type',
                Issue::at(
                    sprintf(
                        'Doctrine %s relation to %s is incompatible with property type %s::$%s (%s).',
                        $property->toMany ? 'to-many' : 'to-one',
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
