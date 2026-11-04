<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryBuilderState;
use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryResultTypeResolver;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

final class QueryBuilderReturnTypeProvider implements MethodReturnTypeProvider
{
    private readonly QueryResultTypeResolver $resolver;

    public function __construct(EntityMetadataRepository $metadata, DoctrineConfig $config)
    {
        $this->resolver = new QueryResultTypeResolver($metadata, $config);
    }

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'createQueryBuilder'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManager', 'createQueryBuilder'),
            ...array_map(
                static fn (string $method): MethodTarget => MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', $method),
                [
                    'select', 'addSelect', 'from', 'join', 'innerJoin', 'leftJoin', 'where', 'andWhere', 'orWhere',
                    'groupBy', 'addGroupBy', 'orderBy', 'addOrderBy', 'getQuery', 'getDQL', 'indexBy', 'distinct',
                ],
            ),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $method = strtolower($context->invocation->name);
        if ($method === 'createquerybuilder') {
            return QueryBuilderState::initialType();
        }

        if ($method === 'getdql') {
            $dql = QueryBuilderState::dql($context->invocation->receiverType);

            return $dql === null ? null : Type::literalString($dql);
        }

        if ($method === 'getquery') {
            $dql = QueryBuilderState::dql($context->invocation->receiverType);
            if ($dql === null) {
                return null;
            }

            return Type::namedObject('Doctrine\\ORM\\Query', ...$this->resolver->resolveQueryParameters($dql));
        }

        return QueryBuilderState::appendInvocation($context->invocation);
    }
}