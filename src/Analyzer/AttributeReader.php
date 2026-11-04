<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

final class AttributeReader
{
    /**
     * @param list<AttributeMetadata> $attributes
     */
    public static function find(array $attributes, string $name): ?AttributeMetadata
    {
        foreach ($attributes as $attribute) {
            if (strcasecmp(ltrim($attribute->name, '\\'), ltrim($name, '\\')) === 0) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @param list<AttributeMetadata> $attributes
     */
    public static function has(array $attributes, string $name): bool
    {
        return self::find($attributes, $name) !== null;
    }

    public static function stringArgument(
        ?AttributeMetadata $attribute,
        int $position,
        string ...$names,
    ): ?string {
        $type = $attribute?->getArgument($position, ...$names)?->valueType;

        return $type?->getLiteralClassString() ?? $type?->getLiteralString();
    }

    public static function boolArgument(?AttributeMetadata $attribute, int $position, string ...$names): ?bool
    {
        return $attribute?->getArgument($position, ...$names)?->valueType?->getLiteralBool();
    }

    public static function classFromType(?Type $type): ?string
    {
        if ($type === null || count($type->atomicTypes) !== 1) {
            return null;
        }

        $atomicType = $type->atomicTypes[0];

        return $atomicType instanceof NamedObjectType ? $atomicType->name : null;
    }
}