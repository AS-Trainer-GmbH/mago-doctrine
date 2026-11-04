<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryResultTypeResolver;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use PHPUnit\Framework\Attributes\DataProvider;

final class QueryResultTypeResolverTest extends DqlTestCase
{
    public function testSelectingAnEntityAliasReturnsTheEntity(): void
    {
        self::assertSame('Acme\\User', (string) $this->resolve('SELECT u FROM Acme\\User u'));
        self::assertSame('Acme\\User', (string) $this->resolve('SELECT DISTINCT u FROM User u WHERE u.id = 1'));
        self::assertSame('Acme\\Group', (string) $this->resolve('SELECT g FROM Acme\\User u JOIN u.group g'));
    }

    /** @param array<string, string> $expected */
    #[DataProvider('scalarQueries')]
    public function testScalarSelectionsReturnShapes(string $dql, array $expected): void
    {
        self::assertSame($expected, $this->shape($this->resolve($dql)));
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function scalarQueries(): iterable
    {
        yield 'fields' => ['SELECT u.id, u.email, u.name FROM Acme\\User u', ['id' => 'int', 'email' => 'string', 'name' => 'string|null']];
        yield 'coalesce removes null' => ["SELECT COALESCE(u.name, 'Anonymous') AS name FROM Acme\\User u", ['name' => 'string']];
        yield 'aggregates' => ['SELECT COUNT(u.id) AS total, MAX(u.id) AS highest FROM Acme\\User u', ['total' => 'int', 'highest' => 'int|null']];
        yield 'identity' => ['SELECT IDENTITY(u.group) AS groupId FROM Acme\\User u', ['groupId' => 'int|null']];
        yield 'custom function' => ['SELECT FLOOR(u.id) AS rounded FROM Acme\\User u', ['rounded' => 'int']];
        yield 'mixed entity and scalar' => ['SELECT u, COUNT(g.id) AS groups FROM Acme\\User u JOIN u.group g', ['0' => 'Acme\\User', 'groups' => 'int']];
        yield 'new object' => ['SELECT NEW Acme\\Dto(u.id) AS dto FROM Acme\\User u', ['dto' => 'Acme\\Dto']];
    }

    public function testIndexByChangesTheKeyType(): void
    {
        $resolver = new QueryResultTypeResolver($this->metadata(), $this->config());

        [$key, $result] = $resolver->resolveQueryParameters('SELECT u FROM Acme\\User u INDEX BY u.email');

        self::assertSame('string', (string) $key);
        self::assertSame('Acme\\User', (string) $result);
    }

    public function testUnknownEntitiesAndSubqueriesAreMixed(): void
    {
        self::assertSame('mixed', (string) $this->resolve('SELECT x FROM Acme\\Missing x'));
        self::assertSame('mixed', (string) $this->resolve('SELECT u FROM Acme\\User u WHERE u.id IN (SELECT g.id FROM Acme\\Group g)'));
    }

    private function resolve(string $dql): Type
    {
        return (new QueryResultTypeResolver($this->metadata(), $this->config()))->resolve($dql);
    }

    /** @return array<string, string> */
    private function shape(Type $type): array
    {
        $atomic = $type->atomicTypes[0];
        self::assertInstanceOf(KeyedArrayType::class, $atomic);

        $shape = [];
        foreach ($atomic->knownItems ?? [] as $item) {
            self::assertInstanceOf(ArrayItem::class, $item);
            $shape[(string) $item->key->value] = (string) $item->type;
        }

        return $shape;
    }
}
