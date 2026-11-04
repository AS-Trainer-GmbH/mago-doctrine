<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityColumnRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityConstructorNotFinalRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityMappingRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityNotFinalRule;
use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\EntityRelationRule;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Analyzer\BeforeAnalysisHook;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

final class EntityAnalysisHook implements BeforeAnalysisHook
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    public function beforeAnalysis(BeforeAnalysisContext $context): void
    {
        $this->metadata->load($context->codebase);

        foreach ($this->metadata->getEntities() as $entity) {
            foreach ($entity->properties as $property) {
                if (!$property->mapped) {
                    continue;
                }

                $identifier = new MemberIdentifier($entity->className, '$' . $property->name);
                $context->references->addPropertyRead($entity->className, $identifier);
                if ($property->frameworkManaged || $property->generated || $entity->readOnly) {
                    $context->references->addPropertyWrite($entity->className, $identifier);
                }
            }

            EntityColumnRule::check($entity, $context, $this->config);
            EntityRelationRule::check($entity, $context);
            EntityMappingRule::check($entity, $context);
            if ($this->config->reportFinalEntities && !$this->config->nativeLazyObjects && !$entity->odm) {
                EntityNotFinalRule::check($entity, $context);
            }
            if (!$this->config->nativeLazyObjects && !$entity->odm) {
                EntityConstructorNotFinalRule::check($entity, $context);
            }
        }
    }
}