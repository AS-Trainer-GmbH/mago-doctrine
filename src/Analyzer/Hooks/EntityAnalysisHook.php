<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Analyzer\BeforeAnalysisHook;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

final class EntityAnalysisHook implements BeforeAnalysisHook
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
    ) {}

    public function beforeAnalysis(BeforeAnalysisContext $context): void
    {
        $this->metadata->load($context->codebase);

        foreach ($this->metadata->getEntities() as $entity) {
            foreach ($entity->properties as $property) {
                if (!$property->mapped) {
                    continue;
                }

                $declaringClass = $property->declaringClass ?? $entity->className;
                $identifier = new MemberIdentifier($declaringClass, '$' . $property->name);
                $context->references->addPropertyRead($declaringClass, $identifier);
                if ($property->frameworkManaged || $property->generated || $entity->readOnly) {
                    $context->references->addPropertyWrite($declaringClass, $identifier);
                }
            }
        }
    }
}