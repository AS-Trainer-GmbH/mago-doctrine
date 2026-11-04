<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\EnumType;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function array_filter;
use function array_values;
use function count;
use function ltrim;
use function strtolower;

/**
 * Infers the DBAL type of a column without explicit `type`, like Doctrine's `DefaultTypedFieldMapper`.
 */
final class ColumnTypeInferrer
{
    private const DEFAULT_TYPE = 'string';

    /**
     * @return array{string, ?string} DBAL type and inferred enum class
     */
    public static function infer(?Type $nativeType, ?string $enumType = null, ?Codebase $codebase = null): array
    {
        if ($enumType !== null) {
            return [self::enumBackingType($enumType, $codebase) ?? self::DEFAULT_TYPE, null];
        }

        $atomicType = self::singleNonNullAtomic($nativeType);
        if ($atomicType === null) {
            return [self::DEFAULT_TYPE, null];
        }

        if ($atomicType instanceof ScalarType) {
            return [match ($atomicType->kind) {
                ScalarTypeKind::Boolean => 'boolean',
                ScalarTypeKind::Integer => 'integer',
                ScalarTypeKind::Float => 'float',
                default => self::DEFAULT_TYPE,
            }, null];
        }

        if ($atomicType instanceof KeyedArrayType || $atomicType instanceof ListType) {
            return ['json', null];
        }

        if ($atomicType instanceof NamedObjectType || $atomicType instanceof EnumType) {
            $className = ltrim($atomicType->name, '\\');
            $type = match (strtolower($className)) {
                'dateinterval' => 'dateinterval',
                'datetime' => 'datetime',
                'datetimeimmutable' => 'datetime_immutable',
                default => null,
            };
            if ($type !== null) {
                return [$type, null];
            }

            $backingType = self::enumBackingType($className, $codebase);
            if ($backingType !== null) {
                return [$backingType, $className];
            }
        }

        return [self::DEFAULT_TYPE, null];
    }

    private static function singleNonNullAtomic(?Type $type): ?AtomicType
    {
        if ($type === null) {
            return null;
        }

        $atomicTypes = array_values(array_filter(
            $type->atomicTypes,
            static fn (AtomicType $atomicType): bool => !($atomicType instanceof SimpleAtomicType && $atomicType->kind === SimpleAtomicTypeKind::Null),
        ));

        $booleans = array_filter(
            $atomicTypes,
            static fn (AtomicType $atomicType): bool => $atomicType instanceof ScalarType && $atomicType->kind === ScalarTypeKind::Boolean,
        );
        if ($atomicTypes !== [] && count($booleans) === count($atomicTypes)) {
            return new ScalarType(ScalarTypeKind::Boolean);
        }

        return count($atomicTypes) === 1 ? $atomicTypes[0] : null;
    }

    private static function enumBackingType(string $enumClass, ?Codebase $codebase): ?string
    {
        $backingType = $codebase?->getEnum($enumClass)?->enumType;
        $atomicType = self::singleNonNullAtomic($backingType);
        if (!$atomicType instanceof ScalarType) {
            return null;
        }

        return match ($atomicType->kind) {
            ScalarTypeKind::Integer => 'integer',
            ScalarTypeKind::String => 'string',
            default => null,
        };
    }
}
