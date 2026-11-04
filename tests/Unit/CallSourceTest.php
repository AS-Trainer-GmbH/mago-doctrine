<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\CallSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallSourceTest extends TestCase
{
    #[DataProvider('calls')]
    public function testFindsTheOutermostMethodName(string $call, ?string $expected): void
    {
        self::assertSame($expected, CallSource::methodName($call));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function calls(): iterable
    {
        yield 'simple' => ['$repository->findBy([\'a\' => 1])', 'findBy'];
        yield 'chained' => ['$manager->getRepository(User::class)->findOneBy([\'email\' => \'x\'])', 'findOneBy'];
        yield 'multiline chain' => ["\$qb\n    ->select('u')\n    ->getQuery()", 'getQuery'];
        yield 'parenthesis in string' => ['$manager->createQuery(\'SELECT COUNT(u) FROM User u\')', 'createQuery'];
        yield 'static' => ['Foo::bar()', 'bar'];
        yield 'not a call' => ['$foo', null];
    }
}
