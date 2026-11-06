<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\LifecycleContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

final class EntityConstructorNotFinalRule
{
    public static function check(EntityMetadata $entity, LifecycleContext $context, string $file): void
    {
        $constructor = $context->codebase->getDeclaringMethod($entity->className, '__construct');
        if (
            $constructor === null
            || !$constructor->final
            || !FileLocationMatcher::matches($constructor->nameLocation ?? $constructor->location, $file)
        ) {
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
