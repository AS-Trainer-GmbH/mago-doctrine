<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryBuilderState;
use Mago\Sdk\Analyzer\Assertion\TypeAssertion;
use Mago\Sdk\Analyzer\Assertion\TypeAssertionKind;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\AssertionProviderContext;

final class QueryBuilderStateAssertionProvider implements MethodAssertionProvider
{
    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'select'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'addSelect'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'from'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'join'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'innerJoin'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'leftJoin'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'where'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'andWhere'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'orWhere'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'groupBy'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'addGroupBy'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'orderBy'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'addOrderBy'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'indexBy'),
            MethodTarget::exact('Doctrine\\ORM\\QueryBuilder', 'distinct'),
        ];
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        $state = QueryBuilderState::appendInvocation($context->invocation);

        return new InvocationAssertions(assertions: [
            InvocationAssertions::RECEIVER => [
                new TypeAssertion(
                    TypeAssertionKind::IsType,
                    \Mago\Sdk\Analyzer\Type::namedObject('Doctrine\\ORM\\QueryBuilder', $state->atomicTypes[0]->parameters[0]),
                ),
            ],
        ]);
    }
}