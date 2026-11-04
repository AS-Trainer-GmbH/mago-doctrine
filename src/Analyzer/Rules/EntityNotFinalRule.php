<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Rules;

use Astrainer\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityNotFinalRule
{
    public static function check(EntityMetadata $entity, BeforeAnalysisContext $context): void
    {
        $location = $entity->nameLocation ?? $entity->location;
        if (!$entity->final || $location === null) {
            return;
        }

        $context->report(
            Level::Error,
            'entity-not-final',
            Issue::at(
                sprintf('Doctrine entity %s must not be final because it may need to be proxied.', $entity->className),
                $location,
            ),
        );
    }
}