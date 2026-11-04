<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function is_string;
use function ltrim;
use function strcasecmp;

/**
 * Resolves registered Doctrine type classes to their PHP conversion value types.
 */
final class TypeDescriptorRegistry
{
    /** @var array<string, class-string> */
    private const CLASS_DESCRIPTORS = [
        'Symfony\\Bridge\\Doctrine\\Types\\UuidType' => 'Symfony\\Component\\Uid\\Uuid',
        'Symfony\\Bridge\\Doctrine\\Types\\UlidType' => 'Symfony\\Component\\Uid\\Ulid',
        'Ramsey\\Uuid\\Doctrine\\UuidType' => 'Ramsey\\Uuid\\UuidInterface',
        'Ramsey\\Uuid\\Doctrine\\UuidBinaryType' => 'Ramsey\\Uuid\\UuidInterface',
        'Ramsey\\Uuid\\Doctrine\\UuidBinaryOrderedTimeType' => 'Ramsey\\Uuid\\UuidInterface',
        'Carbon\\Doctrine\\DateTimeType' => 'Carbon\\Carbon',
        'Carbon\\Doctrine\\DateTimeImmutableType' => 'Carbon\\CarbonImmutable',
    ];

    /**
     * @param array<string, Type|string> $customTypes
     * @param array<string, class-string> $types
     */
    public function __construct(
        private readonly array $customTypes = [],
        private readonly array $types = [],
    ) {}

    public function toPhpType(
        string $dbalType,
        ?string $enumType = null,
        ?string $typeClass = null,
        ?Codebase $codebase = null,
    ): ?Type {
        if ($enumType !== null) {
            return Type::namedObject($enumType);
        }

        $customType = $this->customTypes[$dbalType] ?? null;
        if ($customType instanceof Type) {
            return $customType;
        }

        $configuredClass = $this->types[$dbalType] ?? null;
        if (is_string($customType)) {
            $configuredClass = $customType;
        }
        if ($configuredClass !== null) {
            $type = $this->classType($configuredClass, $codebase);
            if ($type !== null) {
                return $type;
            }
        }

        if ($typeClass !== null) {
            $type = $this->classType($typeClass, $codebase);
            if ($type !== null) {
                return $type;
            }
        }

        return DoctrineTypeMapper::toPhpType($dbalType);
    }

    private function classType(string $class, ?Codebase $codebase): ?Type
    {
        $type = $this->classDescriptor($class, $codebase, []);
        if ($type !== null) {
            return $type;
        }

        $method = $codebase?->getMethod($class, 'convertToPHPValue');
        $returnType = $method?->returnType?->type ?? $method?->declaredReturnType?->type;

        return $returnType === null ? null : self::withoutNull($returnType);
    }

    /** @param list<string> $visited */
    private function classDescriptor(string $class, ?Codebase $codebase, array $visited): ?Type
    {
        $class = ltrim($class, '\\');
        foreach (self::CLASS_DESCRIPTORS as $typeClass => $phpClass) {
            if (strcasecmp($class, $typeClass) === 0) {
                return Type::namedObject($phpClass);
            }
        }

        if ($codebase === null || self::containsClass($class, $visited)) {
            return null;
        }
        $visited[] = $class;

        $metadata = $codebase->getClass($class);
        if ($metadata === null) {
            return null;
        }

        foreach ($metadata->parentClasses as $parent) {
            $type = $this->classDescriptor($parent, $codebase, $visited);
            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    /** @param list<string> $classes */
    private static function containsClass(string $class, array $classes): bool
    {
        foreach ($classes as $candidate) {
            if (strcasecmp($class, $candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function withoutNull(Type $type): Type
    {
        $atomicTypes = array_values(array_filter(
            $type->atomicTypes,
            static fn (AtomicType $atomicType): bool => !(
                $atomicType instanceof SimpleAtomicType && $atomicType->kind === SimpleAtomicTypeKind::Null
            ),
        ));

        if ($atomicTypes === [] || count($atomicTypes) === count($type->atomicTypes)) {
            return $type;
        }

        $types = array_map(static fn (AtomicType $atomicType): Type => Type::fromAtomic($atomicType), $atomicTypes);

        return count($types) === 1 ? $types[0] : Type::union($types[0], $types[1], ...array_slice($types, 2));
    }
}