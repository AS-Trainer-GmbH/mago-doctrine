<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\DbalTypeMapLoader;
use Gimmickmedia\Mago\Doctrine\Analyzer\ObjectManagerLoader;
use Gimmickmedia\Mago\Doctrine\Analyzer\TypeDescriptorRegistry;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use PHPUnit\Framework\TestCase;

final class DbalTypeMapLoaderTest extends TestCase
{
    public function testLoadsSymfonyDbalTypeRegistrationsAndResolvesTheirDescriptors(): void
    {
        $config = new DoctrineConfig(symfonyConfigPaths: [dirname(__DIR__, 2) . '/tests/corpus/config/packages/doctrine.yaml']);
        $types = (new DbalTypeMapLoader($config, new ObjectManagerLoader($config)))->load();

        self::assertSame('\\Carbon\\Doctrine\\DateTimeImmutableType', $types['datetime_immutable']);
        self::assertSame('Corpus\\Doctrine\\CustomValueType', $types['custom_value']);
        self::assertSame(
            'Carbon\\CarbonImmutable',
            (string) (new TypeDescriptorRegistry(types: $types))->toPhpType('datetime_immutable'),
        );
    }

    public function testExplicitTypeRegistrationsOverrideSymfonyConfig(): void
    {
        $config = new DoctrineConfig(
            types: ['datetime_immutable' => 'App\\Doctrine\\DateTimeType'],
            symfonyConfigPaths: [dirname(__DIR__, 2) . '/tests/corpus/config/packages/doctrine.yaml'],
        );
        $types = (new DbalTypeMapLoader($config, new ObjectManagerLoader($config)))->load();

        self::assertSame('App\\Doctrine\\DateTimeType', $types['datetime_immutable']);
    }

    public function testMissingSymfonyConfigReturnsNoTypes(): void
    {
        $config = new DoctrineConfig(symfonyConfigPaths: ['/missing/project/config/packages/doctrine.yaml']);

        self::assertSame([], (new DbalTypeMapLoader($config, new ObjectManagerLoader($config)))->load());
    }

    public function testEmptyConfigPathsDisableAutomaticDiscovery(): void
    {
        $config = new DoctrineConfig(symfonyConfigPaths: []);

        self::assertSame([], (new DbalTypeMapLoader($config, new ObjectManagerLoader($config)))->load());
    }
}