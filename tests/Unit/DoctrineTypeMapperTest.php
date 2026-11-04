<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\DoctrineTypeMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctrineTypeMapperTest extends TestCase
{
    #[DataProvider('dbalTypes')]
    public function testMapsDbalTypesToPhpTypes(string $dbalType, string $expected): void
    {
        self::assertSame($expected, (string) DoctrineTypeMapper::toPhpType($dbalType));
    }

    public function testMapsEnumColumnsToTheEnumClass(): void
    {
        self::assertSame(
            'Corpus\\Status',
            (string) DoctrineTypeMapper::toPhpType('string', 'Corpus\\Status'),
        );
    }

    public function testUnknownDbalTypesAreNotGuessed(): void
    {
        self::assertNull(DoctrineTypeMapper::toPhpType('custom_type'));
    }

    public function testNullableMappingsIncludeNull(): void
    {
        self::assertSame('string|null', (string) DoctrineTypeMapper::nullable(DoctrineTypeMapper::toPhpType('string'), true));
        self::assertSame('string', (string) DoctrineTypeMapper::nullable(DoctrineTypeMapper::toPhpType('string'), false));
    }

    /** @return iterable<string, array{string, string}> */
    public static function dbalTypes(): iterable
    {
        yield 'integer' => ['integer', 'int'];
        yield 'boolean' => ['boolean', 'bool'];
        yield 'string' => ['string', 'string'];
        yield 'bigint' => ['bigint', 'int|string'];
        yield 'decimal' => ['decimal', 'string'];
        yield 'immutable datetime' => ['datetime_immutable', 'DateTimeImmutable'];
        yield 'simple array' => ['simple_array', 'list<string>'];
        yield 'json' => ['json', 'array<mixed, mixed>'];
    }
}