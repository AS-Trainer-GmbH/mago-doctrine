<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityColumnRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityConstructorNotFinalRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityMappingRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityNotFinalRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityRelationRule;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\AfterFileAnalysisContext;
use Mago\Sdk\Analyzer\AfterFileAnalysisHook;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;

final class EntityMappingReportHook implements AfterFileAnalysisHook
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    /** @return list<FileAnalysisRequirement> */
    public function getRequirements(): array
    {
        return [];
    }

    public function afterFileAnalysis(AfterFileAnalysisContext $context): void
    {
        $this->metadata->load($context->codebase);
        $file = $context->analysis->file;

        foreach ($this->metadata->getEntities() as $entity) {
            EntityColumnRule::check($entity, $context, $this->config, $file);
            EntityRelationRule::check($entity, $context, $file);
            EntityMappingRule::check($entity, $context, $file);
            if ($this->config->reportFinalEntities && !$this->config->nativeLazyObjects && !$entity->odm) {
                EntityNotFinalRule::check($entity, $context, $file);
            }
            if (!$this->config->nativeLazyObjects && !$entity->odm) {
                EntityConstructorNotFinalRule::check($entity, $context, $file);
            }
        }
    }
}