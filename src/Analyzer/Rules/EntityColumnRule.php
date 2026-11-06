<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\DoctrineTypeMapper;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Gimmickmedia\Mago\Doctrine\Analyzer\TypeDescriptorRegistry;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\LifecycleContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;

use function in_array;
use function strtolower;

final class EntityColumnRule
{
    public static function check(EntityMetadata $entity, LifecycleContext $context, DoctrineConfig $config, string $file): void
    {
        $typeDescriptors = new TypeDescriptorRegistry($config->customTypes, $config->types);
        foreach ($entity->properties as $property) {
            if (
                !$property->mapped
                || $property->columnType === null
                || $property->location === null
                || !FileLocationMatcher::matches($property->location, $file)
            ) {
                continue;
            }

            $mappedType = $property->columnPhpType
                ?? $typeDescriptors->toPhpType(
                    $property->columnType,
                    $property->enumType,
                    $property->columnTypeClass,
                    $context->codebase,
                );
            if ($mappedType === null) {
                if ($config->reportUnknownTypes) {
                    $context->report(
                        Level::Error,
                        'entity-unknown-column-type',
                        Issue::at(
                            sprintf(
                                'Doctrine column type %s of %s::$%s has no known type descriptor.',
                                $property->columnType,
                                $entity->className,
                                $property->name,
                            ),
                            $property->location,
                        ),
                    );
                }

                continue;
            }

            if ($property->nativeType === null) {
                continue;
            }

            $expectedType = DoctrineTypeMapper::nullable($mappedType, $property->nullable);
            if (
                !in_array(strtolower($property->columnType), ['json', 'json_array'], true)
                && (
                    (string) $expectedType !== (string) $property->nativeType
                    && !$context->types->isContainedBy($expectedType, $property->nativeType)
                )
            ) {
                self::report($context, $entity, $property->name, $property->columnType, (string) $property->nativeType, $property->location, 'is incompatible with');

                continue;
            }

            if (
                !$property->nullable
                && !$property->identifier
                && !$config->allowNullablePropertyForRequiredField
                && $context->types->isContainedBy(Type::null(), $property->nativeType)
            ) {
                self::report($context, $entity, $property->name, $property->columnType, (string) $property->nativeType, $property->location, 'is not nullable but is mapped to nullable');
            }
        }
    }

    private static function report(
        LifecycleContext $context,
        EntityMetadata $entity,
        string $property,
        string $columnType,
        string $nativeType,
        SourceLocation $location,
        string $relation,
    ): void {
        $context->report(
            Level::Error,
            'entity-column-type',
            Issue::at(
                sprintf(
                    'Doctrine column type %s %s property type %s::$%s (%s).',
                    $columnType,
                    $relation,
                    $entity->className,
                    $property,
                    $nativeType,
                ),
                $location,
            ),
        );
    }
}
