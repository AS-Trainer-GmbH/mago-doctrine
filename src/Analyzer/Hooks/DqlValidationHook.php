<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Gimmickmedia\Mago\Doctrine\Analyzer\CallSource;
use Gimmickmedia\Mago\Doctrine\Analyzer\ObjectManagerLoader;
use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\DqlValidator;
use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\ObjectManagerDqlValidator;
use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryBuilderState;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;

/**
 * Validates DQL passed to `createQuery()` and built by `QueryBuilder::getQuery()`, reports dynamic
 * query builders (when enabled) and non-literal SQL/DQL strings (when `literalString` is enabled).
 */
final class DqlValidationHook implements MethodCallAnalysisHook
{
    private const LITERAL_STRING_METHODS = [
        'createquery', 'executequery', 'executestatement', 'executeupdate', 'prepare', 'query', 'exec',
        'fetchallassociative', 'fetchassociative', 'fetchone', 'fetchallnumeric', 'fetchnumeric', 'fetchfirstcolumn',
    ];

    private readonly DqlValidator $validator;

    private readonly ObjectManagerDqlValidator $objectManagerValidator;

    public function __construct(
        private readonly DoctrineConfig $config,
        private readonly EntityMetadataRepository $metadata,
        ?ObjectManagerLoader $objectManagerLoader = null,
    ) {
        $this->validator = new DqlValidator($metadata, $config);
        $this->objectManagerValidator = new ObjectManagerDqlValidator($config, $objectManagerLoader);
    }

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        $targets = [
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'createQuery'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManager', 'createQuery'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'getQuery'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'getDQL'),
        ];

        if ($this->config->literalString) {
            foreach (['executeQuery', 'executeStatement', 'executeUpdate', 'prepare', 'query', 'exec', 'fetchAllAssociative', 'fetchAssociative', 'fetchOne', 'fetchAllNumeric', 'fetchNumeric', 'fetchFirstColumn'] as $method) {
                $targets[] = MethodTarget::exact('Doctrine\\DBAL\\Connection', $method);
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
        $span = $context->node->span;
        $method = strtolower(CallSource::methodName(substr($context->source->contents, $span->start, $span->end - $span->start)) ?? '');
        $location = new SourceLocation($context->source->path, $span);

        if ($this->config->literalString && in_array($method, self::LITERAL_STRING_METHODS, true)) {
            $argument = $context->argumentTypes[0] ?? null;
            if ($argument !== null && $argument->getLiteralString() === null && !$this->isLiteralString($argument)) {
                $context->report(
                    Level::Error,
                    'literal-string',
                    Issue::at(sprintf('Argument #1 of %s() must be a literal-string, %s given.', $method, $argument), $location),
                );
            }
        }

        $this->metadata->load($context->codebase);
        if ($method === 'createquery') {
            $dql = ($context->argumentTypes[0] ?? null)?->getLiteralString();
        } elseif ($method === 'getquery' || $method === 'getdql') {
            $dql = QueryBuilderState::dql($context->receiverType);
            if ($dql === null && $this->config->reportDynamicQueryBuilders) {
                $context->report(
                    Level::Error,
                    'dynamic-query-builder',
                    Issue::at('Could not analyse QueryBuilder with dynamic arguments; the query result type cannot be inferred.', $location),
                );
            }
        } else {
            return;
        }

        if ($dql === null) {
            return;
        }

        foreach (array_unique([...$this->validator->validate($dql), ...$this->objectManagerValidator->validate($dql)]) as $error) {
            $context->report(Level::Error, 'dql-validation', Issue::at($error, $location));
        }
    }

    private function isLiteralString(\Mago\Sdk\Analyzer\Type $type): bool
    {
        foreach ($type->atomicTypes as $atomicType) {
            if (!$atomicType instanceof \Mago\Sdk\Analyzer\Type\ScalarType || $atomicType->kind !== \Mago\Sdk\Analyzer\Type\ScalarTypeKind::String) {
                return false;
            }

            $string = $atomicType->refinement;
            if (!$string instanceof \Mago\Sdk\Analyzer\Type\StringType || $string->literalKind === \Mago\Sdk\Analyzer\Type\StringLiteralKind::General) {
                return false;
            }
        }

        return true;
    }
}
