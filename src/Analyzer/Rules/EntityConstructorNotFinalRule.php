<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityConstructorNotFinalRule
{
    public static function check(EntityMetadata $entity, BeforeAnalysisContext $context): void
    {
        $constructor = $context->codebase->getDeclaringMethod($entity->className, '__construct');
        if ($constructor === null || !$constructor->final) {
            return;
        }

        $context->report(
            Level::Error,
            'entity-constructor-not-final',
            Issue::at(
                sprintf('Constructor of Doctrine entity %s must not be final because it may need to be proxied.', $entity->className),
                $constructor->nameLocation ?? $constructor->location,
            ),
        );
    }
}
