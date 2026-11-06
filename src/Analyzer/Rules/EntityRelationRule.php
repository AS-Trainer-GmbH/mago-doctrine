<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\LifecycleContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function in_array;
use function ltrim;
use function str_contains;
use function strtolower;

final class EntityRelationRule
{
    public static function check(EntityMetadata $entity, LifecycleContext $context, string $file): void
    {
        foreach ($entity->properties as $property) {
            if (
                !$property->mapped
                || $property->targetEntity === null
                || $property->columnType !== null
                || $property->nativeType === null
                || $property->location === null
                || !FileLocationMatcher::matches($property->location, $file)
            ) {
                continue;
            }

            if ($property->toMany) {
                $keyType = Type::mixed();
                foreach ($property->nativeType->atomicTypes as $atomicType) {
                    if (
                        $atomicType instanceof NamedObjectType
                        && self::isCollectionType($atomicType->name, $context)
                    ) {
                        $keyType = $atomicType->parameters[0] ?? Type::mixed();
                        break;
                    }
                }
                $expectedType = Type::namedObject(
                    'Doctrine\\Common\\Collections\\Collection',
                    $keyType,
                    Type::namedObject($property->targetEntity),
                );
            } else {
                $relationType = Type::namedObject($property->targetEntity);
                $expectedType = $property->nullable ? Type::union($relationType, Type::null()) : $relationType;
            }

            if (
                $context->types->isContainedBy($expectedType, $property->nativeType)
                || ($property->toMany && self::hasCompatibleCollectionElement($property->nativeType, $property->targetEntity, $context))
            ) {
                continue;
            }

            $context->report(
                Level::Error,
                'entity-relation-type',
                Issue::at(
                    sprintf(
                        'Doctrine %s relation to %s (%s %s) is incompatible with property type %s::$%s (%s).',
                        $property->toMany ? 'to-many' : 'to-one',
                        $property->targetEntity,
                        $property->toMany ? 'expects' : 'database can contain',
                        $expectedType,
                        $entity->className,
                        $property->name,
                        $property->nativeType,
                    ),
                    $property->location,
                ),
            );
        }
    }

    private static function hasCompatibleCollectionElement(Type $type, string $targetEntity, LifecycleContext $context): bool
    {
        $typeName = strtolower(ltrim((string) $type, '\\'));
        if (in_array($typeName, [
            'doctrine\\common\\collections\\collection',
            'doctrine\\common\\collections\\readablecollection',
            'doctrine\\common\\collections\\arraycollection',
        ], true)) {
            return true;
        }
        if (
            !str_contains($typeName, '<')
            && $context->types->isContainedBy(
                Type::namedObject('Doctrine\\Common\\Collections\\Collection'),
                $type,
            )
        ) {
            return true;
        }

        foreach ($type->atomicTypes as $atomicType) {
            if (!$atomicType instanceof NamedObjectType || !self::isCollectionType($atomicType->name, $context)) {
                continue;
            }

            $elementType = $atomicType->parameters[1] ?? null;
            if (
                $elementType === null
                || (string) $elementType === 'mixed'
                || $context->types->isContainedBy(Type::namedObject($targetEntity), $elementType)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function isCollectionType(string $class, LifecycleContext $context): bool
    {
        $class = strtolower(ltrim($class, '\\'));
        if (in_array($class, [
            'doctrine\\common\\collections\\collection',
            'doctrine\\common\\collections\\readablecollection',
            'doctrine\\common\\collections\\arraycollection',
        ], true)) {
            return true;
        }

        return $context->types->isContainedBy(
            Type::namedObject($class),
            Type::namedObject('Doctrine\\Common\\Collections\\Collection'),
        );
    }
}
