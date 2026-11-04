<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\AttributeReader;
use Mago\Sdk\Analyzer\Metadata\AttributeArgumentMetadata;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

final class AttributeReaderTest extends TestCase
{
    public function testFindsResolvedAttributeNamesWithoutCaseSensitivity(): void
    {
        $attribute = $this->attribute('Doctrine\\ORM\\Mapping\\Entity');

        self::assertSame($attribute, AttributeReader::find([$attribute], 'doctrine\\orm\\mapping\\entity'));
        self::assertTrue(AttributeReader::has([$attribute], 'Doctrine\\ORM\\Mapping\\Entity'));
        self::assertFalse(AttributeReader::has([$attribute], 'Doctrine\\ORM\\Mapping\\Column'));
    }

    public function testReadsPositionalAndNamedAttributeArguments(): void
    {
        $attribute = $this->attribute('Doctrine\\ORM\\Mapping\\Entity', [
            new AttributeArgumentMetadata('repositoryClass', $this->location(), null, null, Type::literalString('Corpus\\UserRepository')),
            new AttributeArgumentMetadata('readOnly', $this->location(), null, null, Type::true()),
        ]);

        self::assertSame('Corpus\\UserRepository', AttributeReader::stringArgument($attribute, 0, 'repositoryClass'));
        self::assertTrue(AttributeReader::boolArgument($attribute, 1, 'readOnly'));
    }

    private function attribute(string $name, array $arguments = []): AttributeMetadata
    {
        return new AttributeMetadata($name, $this->location(), $arguments);
    }

    private function location(): SourceLocation
    {
        return new SourceLocation('test.php', new Span(0, 0));
    }
}