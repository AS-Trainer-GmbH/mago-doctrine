<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\AttributeValueResolver;
use Gimmickmedia\Mago\Doctrine\Analyzer\ColumnTypeInferrer;
use Mago\Sdk\Analyzer\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColumnTypeInferrerTest extends TestCase
{
    #[DataProvider('propertyTypes')]
    public function testInfersColumnTypeFromPropertyType(?Type $propertyType, string $expected): void
    {
        self::assertSame([$expected, null], ColumnTypeInferrer::infer($propertyType));
    }

    /** @return iterable<string, array{?Type, string}> */
    public static function propertyTypes(): iterable
    {
        yield 'untyped' => [null, 'string'];
        yield 'bool' => [Type::bool(), 'boolean'];
        yield 'nullable int' => [Type::union(Type::int(), Type::null()), 'integer'];
        yield 'float' => [Type::float(), 'float'];
        yield 'string' => [Type::string(), 'string'];
        yield 'array' => [Type::array(Type::mixed(), Type::mixed()), 'json'];
        yield 'datetime immutable' => [Type::namedObject(\DateTimeImmutable::class), 'datetime_immutable'];
        yield 'datetime' => [Type::namedObject(\DateTime::class), 'datetime'];
    }

    #[DataProvider('dbalConstants')]
    public function testResolvesDbalTypeConstants(string $constant, string $expected): void
    {
        self::assertSame($expected, AttributeValueResolver::dbalTypeConstant($constant));
    }

    /** @return iterable<string, array{string, string}> */
    public static function dbalConstants(): iterable
    {
        yield 'boolean' => ['BOOLEAN', 'boolean'];
        yield 'datetime immutable' => ['DATETIME_IMMUTABLE', 'datetime_immutable'];
        yield 'datetime mutable' => ['DATETIME_MUTABLE', 'datetime'];
        yield 'date mutable' => ['DATE_MUTABLE', 'date'];
        yield 'json' => ['JSON', 'json'];
    }
}
