<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Hooks;

use Astrainer\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Astrainer\Mago\Doctrine\Analyzer\Rules\EntityColumnRule;
use Astrainer\Mago\Doctrine\Analyzer\Rules\EntityNotFinalRule;
use Astrainer\Mago\Doctrine\Analyzer\Rules\EntityRelationRule;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Analyzer\BeforeAnalysisHook;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

final class EntityAnalysisHook implements BeforeAnalysisHook
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly bool $reportFinalEntities,
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
                if ($property->frameworkManaged) {
                    $context->references->addPropertyWrite($entity->className, $identifier);
                }
            }

            EntityColumnRule::check($entity, $context);
            EntityRelationRule::check($entity, $context);
            if ($this->reportFinalEntities) {
                EntityNotFinalRule::check($entity, $context);
            }
        }
    }
}