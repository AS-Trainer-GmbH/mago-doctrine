<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer;

use Astrainer\Mago\Doctrine\Analyzer\Hooks\EntityAnalysisHook;
use Astrainer\Mago\Doctrine\Analyzer\Providers\EntityPropertyInitializationProvider;
use Astrainer\Mago\Doctrine\Analyzer\Providers\MagicRepositoryMethodsProvider;
use Astrainer\Mago\Doctrine\Analyzer\Providers\RepositoryReturnTypeProvider;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

final class DoctrinePlugin implements Plugin
{
    private readonly EntityMetadataRepository $metadata;

    /**
     * @param list<string> $entityNamespaces
     */
    public function __construct(array $entityNamespaces, private readonly bool $reportFinalEntities)
    {
        $this->metadata = new EntityMetadataRepository($entityNamespaces);
    }

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'astrainer/doctrine',
            name: 'Doctrine ORM analyzer',
            description: 'Understands Doctrine ORM entities and repositories.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerMethodReturnTypeProvider(new RepositoryReturnTypeProvider($this->metadata));
        $registry->registerMethodReturnTypeProvider(new MagicRepositoryMethodsProvider($this->metadata));
        $registry->registerPropertyInitializationProvider(new EntityPropertyInitializationProvider($this->metadata));
        $registry->registerBeforeAnalysisHook(new EntityAnalysisHook($this->metadata, $this->reportFinalEntities));
    }
}