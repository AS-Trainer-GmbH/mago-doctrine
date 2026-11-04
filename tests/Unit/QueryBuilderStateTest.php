<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryBuilderState;
use Mago\Sdk\Analyzer\Type;
use PHPUnit\Framework\TestCase;

final class QueryBuilderStateTest extends TestCase
{
    public function testBuildsDqlFromRecordedOperations(): void
    {
        $state = QueryBuilderState::initialType();
        $state = QueryBuilderState::append($state, 'select', ['u']);
        $state = QueryBuilderState::append($state, 'addselect', ['g']);
        $state = QueryBuilderState::append($state, 'from', ['Acme\\User', 'u', 'u.id']);
        $state = QueryBuilderState::append($state, 'leftjoin', ['u', 'group', 'g']);
        $state = QueryBuilderState::append($state, 'where', ['u.id = :id']);
        $state = QueryBuilderState::append($state, 'orderby', ['u.email', 'DESC']);

        self::assertSame(
            'SELECT u, g FROM Acme\\User u INDEX BY u.id LEFT JOIN u.group g WHERE u.id = :id ORDER BY u.email DESC',
            QueryBuilderState::dql($state),
        );
    }

    public function testSelectDefaultsToTheRootAlias(): void
    {
        $state = QueryBuilderState::append(QueryBuilderState::initialType(), 'from', ['Acme\\User', 'u']);

        self::assertSame('SELECT u FROM Acme\\User u', QueryBuilderState::dql($state));
    }

    public function testUnknownStatesProduceNoDql(): void
    {
        self::assertNull(QueryBuilderState::dql(Type::namedObject('Doctrine\\ORM\\QueryBuilder', Type::mixed())));
        self::assertNull(QueryBuilderState::dql(QueryBuilderState::initialType()));
        self::assertNull(QueryBuilderState::dql(
            QueryBuilderState::append(Type::namedObject('Doctrine\\ORM\\QueryBuilder', Type::mixed()), 'from', ['Acme\\User', 'u']),
        ));
    }
}
