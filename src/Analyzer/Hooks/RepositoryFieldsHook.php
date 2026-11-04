<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Gimmickmedia\Mago\Doctrine\Analyzer\CallSource;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;

/**
 * Validates entity fields in `findBy`, `findOneBy`, `count` and magic `findBy*`/`findOneBy*`/`countBy*` calls.
 */
final class RepositoryFieldsHook implements MethodCallAnalysisHook
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        $targets = [];
        foreach (array_unique([
            'Doctrine\\ORM\\EntityRepository',
            'Doctrine\\Persistence\\ObjectRepository',
            'Doctrine\\ODM\\MongoDB\\Repository\\DocumentRepository',
            $this->config->ormRepositoryClass,
            $this->config->odmRepositoryClass,
        ]) as $class) {
            foreach (['findBy*', 'findOneBy*', 'count*'] as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    /** @return list<FileAnalysisRequirement> */
    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ReceiverType, FileAnalysisRequirement::ArgumentTypes, FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $this->metadata->load($context->codebase);
        $span = $context->node->span;
        $method = CallSource::methodName(substr($context->source->contents, $span->start, $span->end - $span->start));
        if ($method === null) {
            return;
        }

        $repositoryClass = $this->receiverClass($context);
        $entity = $repositoryClass === null
            ? null
            : $this->metadata->getEntityForRepository($repositoryClass, $context->receiverType);
        if ($entity === null) {
            return;
        }

        $fields = [];
        foreach (['findOneBy', 'findBy', 'countBy'] as $prefix) {
            if (strlen($method) > strlen($prefix) && str_starts_with(strtolower($method), strtolower($prefix))) {
                $fields[] = lcfirst(substr($method, strlen($prefix)));
            }
        }

        $lower = strtolower($method);
        if ($fields === [] && in_array($lower, ['findby', 'findoneby', 'count'], true)) {
            foreach ($context->argumentTypes[0]?->atomicTypes ?? [] as $atomicType) {
                if (!$atomicType instanceof KeyedArrayType) {
                    continue;
                }
                foreach ($atomicType->knownItems ?? [] as $item) {
                    if ($item->key->kind === ArrayKeyKind::String) {
                        $fields[] = (string) $item->key->value;
                    }
                }
            }
        }

        foreach ($fields as $field) {
            $property = $entity->getProperty($field);
            if ($property !== null && $property->mapped) {
                continue;
            }

            $context->report(
                Level::Error,
                'repository-unknown-field',
                Issue::at(
                    sprintf('Call to %s() on repository of %s uses unknown field %s.', $method, $entity->className, $field),
                    new SourceLocation($context->source->path, $span),
                ),
            );
        }
    }

    private function receiverClass(NodeAnalysisContext $context): ?string
    {
        foreach ($context->receiverType?->atomicTypes ?? [] as $atomicType) {
            if ($atomicType instanceof \Mago\Sdk\Analyzer\Type\NamedObjectType) {
                return $atomicType->name;
            }
        }

        return null;
    }
}
