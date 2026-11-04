<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Mago\Sdk\Analyzer\Assertion\TypeAssertion;
use Mago\Sdk\Analyzer\Assertion\TypeAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Narrows `Collection::first()` / `last()` from `T|false` to `T` after `isEmpty()` returned false,
 * and types `Collection::matching()` when all collections are selectable.
 */
final class CollectionProvider implements MethodReturnTypeProvider, MethodAssertionProvider
{
    public const COLLECTION = 'Doctrine\\Common\\Collections\\Collection';
    public const READABLE_COLLECTION = 'Doctrine\\Common\\Collections\\ReadableCollection';
    public const NON_EMPTY_MARKER = 'Gimmickmedia\\Mago\\Doctrine\\Stubs\\NonEmptyCollection';

    public function __construct(private readonly bool $allCollectionsSelectable) {}

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::READABLE_COLLECTION, 'isEmpty'),
            MethodTarget::exact(self::READABLE_COLLECTION, 'first'),
            MethodTarget::exact(self::READABLE_COLLECTION, 'last'),
            MethodTarget::exact(self::COLLECTION, 'isEmpty'),
            MethodTarget::exact(self::COLLECTION, 'first'),
            MethodTarget::exact(self::COLLECTION, 'last'),
            MethodTarget::exact(self::COLLECTION, 'matching'),
            MethodTarget::exact(self::READABLE_COLLECTION, 'matching'),
            MethodTarget::anyClass('matching'),
        ];
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        if (strtolower($context->invocation->name) !== 'isempty') {
            return null;
        }

        return new InvocationAssertions(ifFalseAssertions: [
            InvocationAssertions::RECEIVER => [
                new TypeAssertion(TypeAssertionKind::IsType, Type::namedObject(self::NON_EMPTY_MARKER)),
            ],
        ]);
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $method = strtolower($context->invocation->name);
        $receiver = $context->invocation->receiverType;
        $collection = self::findCollection($receiver);

        if ($method === 'matching') {
            if (!$this->allCollectionsSelectable || $collection === null) {
                return null;
            }

            return Type::namedObject(self::COLLECTION, ...($collection->parameters ?? []));
        }

        if (($method !== 'first' && $method !== 'last') || !self::isNonEmpty($receiver)) {
            return null;
        }

        $parameters = $collection?->parameters ?? [];

        return $parameters[1] ?? $parameters[0] ?? null;
    }

    public static function findCollection(?Type $type): ?NamedObjectType
    {
        foreach ($type?->atomicTypes ?? [] as $atomicType) {
            foreach (self::objects($atomicType) as $object) {
                if ($object->parameters !== null && $object->parameters !== [] && strcasecmp($object->name, self::NON_EMPTY_MARKER) !== 0) {
                    return $object;
                }
            }
        }

        return null;
    }

    private static function isNonEmpty(?Type $type): bool
    {
        if ($type === null || $type->atomicTypes === []) {
            return false;
        }

        foreach ($type->atomicTypes as $atomicType) {
            $found = false;
            foreach (self::objects($atomicType) as $object) {
                if (strcasecmp($object->name, self::NON_EMPTY_MARKER) === 0) {
                    $found = true;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /** @return list<NamedObjectType> */
    private static function objects(mixed $atomicType): array
    {
        if (!$atomicType instanceof NamedObjectType) {
            return [];
        }

        $objects = [$atomicType];
        foreach ($atomicType->intersections ?? [] as $intersection) {
            if ($intersection instanceof NamedObjectType) {
                $objects[] = $intersection;
            }
        }

        return $objects;
    }
}
