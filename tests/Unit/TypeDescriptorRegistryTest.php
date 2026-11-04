<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\TypeDescriptorRegistry;
use Mago\Sdk\Analyzer\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypeDescriptorRegistryTest extends TestCase
{
    #[DataProvider('knownDoctrineTypes')]
    public function testResolvesKnownDoctrineTypeClasses(string $typeName, string $typeClass, string $phpClass): void
    {
        $registry = new TypeDescriptorRegistry();

        self::assertSame($phpClass, (string) $registry->toPhpType($typeName, typeClass: $typeClass));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function knownDoctrineTypes(): iterable
    {
        yield 'Symfony UUID' => [
            'uuid',
            'Symfony\\Bridge\\Doctrine\\Types\\UuidType',
            'Symfony\\Component\\Uid\\Uuid',
        ];
        yield 'Symfony ULID' => [
            'ulid',
            'Symfony\\Bridge\\Doctrine\\Types\\UlidType',
            'Symfony\\Component\\Uid\\Ulid',
        ];
        yield 'Ramsey UUID' => [
            'uuid',
            'Ramsey\\Uuid\\Doctrine\\UuidType',
            'Ramsey\\Uuid\\UuidInterface',
        ];
    }

    public function testResolvesConfiguredCarbonTypeClass(): void
    {
        $registry = new TypeDescriptorRegistry(types: [
            'datetime_immutable' => 'Carbon\\Doctrine\\DateTimeImmutableType',
        ]);

        self::assertSame('Carbon\\CarbonImmutable', (string) $registry->toPhpType('datetime_immutable'));
    }

    public function testKeepsDirectCustomPhpTypeMappings(): void
    {
        $registry = new TypeDescriptorRegistry(customTypes: ['money' => Type::namedObject('App\\Money')]);

        self::assertSame('App\\Money', (string) $registry->toPhpType('money'));
    }
}