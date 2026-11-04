<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Providers;

use Astrainer\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Mago\Sdk\Analyzer\PropertyInitializationProvider;
use Mago\Sdk\Analyzer\PropertyInitializationProviderContext;
use Mago\Sdk\Analyzer\PropertyTarget;

final class EntityPropertyInitializationProvider implements PropertyInitializationProvider
{
    public function __construct(private readonly EntityMetadataRepository $metadata) {}

    /** @return non-empty-list<PropertyTarget> */
    public function getTargets(): array
    {
        return [PropertyTarget::allProperties('*')];
    }

    public function isPropertyInitialized(PropertyInitializationProviderContext $context): bool
    {
        $this->metadata->load($context->codebase);

        return $this->metadata->isPropertyInitialized($context->declaringClass, $context->property->name);
    }
}