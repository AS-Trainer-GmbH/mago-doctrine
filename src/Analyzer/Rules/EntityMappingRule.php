<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityMappingRule
{
    public static function check(EntityMetadata $entity, BeforeAnalysisContext $context): void
    {
        $location = $entity->nameLocation ?? $entity->location;
        if (
            $location !== null
            && !str_starts_with($entity->repositoryClass, 'Doctrine\\')
            && !$context->codebase->classExists($entity->repositoryClass)
        ) {
            $context->report(
                Level::Error,
                'entity-mapping',
                Issue::at(
                    sprintf('Repository class %s of Doctrine entity %s does not exist.', $entity->repositoryClass, $entity->className),
                    $location,
                ),
            );
        }

        foreach ($entity->properties as $property) {
            if (
                $property->location === null
                || $property->targetEntity === null
                || $context->codebase->classExists($property->targetEntity)
                || $context->codebase->interfaceExists($property->targetEntity)
            ) {
                continue;
            }

            $context->report(
                Level::Error,
                'entity-mapping',
                Issue::at(
                    sprintf(
                        'Target entity %s of %s::$%s does not exist.',
                        $property->targetEntity,
                        $entity->className,
                        $property->name,
                    ),
                    $property->location,
                ),
            );
        }
    }
}
