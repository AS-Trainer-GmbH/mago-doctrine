<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Gimmickmedia\Mago\Doctrine\Analyzer\Hooks\CollectionMatchingIssueFilter;
use Gimmickmedia\Mago\Doctrine\Analyzer\Hooks\DqlValidationHook;
use Gimmickmedia\Mago\Doctrine\Analyzer\Hooks\EntityAnalysisHook;
use Gimmickmedia\Mago\Doctrine\Analyzer\Hooks\ProxyClassNameHook;
use Gimmickmedia\Mago\Doctrine\Analyzer\Hooks\RepositoryFieldsHook;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\CollectionProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\CreateQueryReturnTypeProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\EntityManagerFindReturnTypeProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\EntityPropertyInitializationProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\FlushThrowTypeProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\MagicRepositoryMethodsProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\QueryBuilderReturnTypeProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\QueryBuilderStateAssertionProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\QueryResultReturnTypeProvider;
use Gimmickmedia\Mago\Doctrine\Analyzer\Providers\RepositoryReturnTypeProvider;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

final class DoctrinePlugin implements Plugin
{
    private readonly EntityMetadataRepository $metadata;

    private readonly ObjectManagerLoader $objectManagerLoader;

    public function __construct(private readonly DoctrineConfig $config)
    {
        $this->objectManagerLoader = new ObjectManagerLoader($config);
        $this->metadata = new EntityMetadataRepository($config, $this->objectManagerLoader);
    }

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'gimmickmedia/doctrine',
            name: 'Doctrine ORM analyzer',
            description: 'Understands Doctrine ORM/ODM entities, repositories, DQL and QueryBuilder queries.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerMethodReturnTypeProvider(new CreateQueryReturnTypeProvider($this->metadata, $this->config));
        $registry->registerMethodReturnTypeProvider(new QueryBuilderReturnTypeProvider($this->metadata, $this->config));
        $registry->registerMethodReturnTypeProvider(new QueryResultReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new RepositoryReturnTypeProvider($this->metadata, $this->config));
        $registry->registerMethodReturnTypeProvider(new EntityManagerFindReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new MagicRepositoryMethodsProvider($this->metadata, $this->config));
        $collections = new CollectionProvider($this->config->allCollectionsSelectable);
        $registry->registerMethodReturnTypeProvider($collections);
        $registry->registerMethodAssertionProvider($collections);
        $registry->registerMethodThrowTypeProvider(new FlushThrowTypeProvider());
        $registry->registerPropertyInitializationProvider(new EntityPropertyInitializationProvider($this->metadata));
        $registry->registerMethodAssertionProvider(new QueryBuilderStateAssertionProvider());
        $registry->registerBeforeAnalysisHook(new EntityAnalysisHook($this->metadata, $this->config));
        $registry->registerMethodCallAnalysisHook(new DqlValidationHook(
            $this->config,
            $this->metadata,
            $this->objectManagerLoader,
        ));
        $registry->registerMethodCallAnalysisHook(new RepositoryFieldsHook($this->metadata, $this->config));
        $registry->registerAfterFileAnalysisHook(new ProxyClassNameHook());
        if ($this->config->allCollectionsSelectable) {
            $registry->registerIssueFilterHook(new CollectionMatchingIssueFilter());
        }
    }
}