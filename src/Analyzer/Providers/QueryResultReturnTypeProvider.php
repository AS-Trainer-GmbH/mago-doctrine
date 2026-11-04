<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Types `getResult()`, `getOneOrNullResult()`, `getSingleResult()`, `toIterable()` and `execute()`
 * of `Doctrine\ORM\Query<TKey, TResult>` in `HYDRATE_OBJECT` mode.
 */
final class QueryResultReturnTypeProvider implements MethodReturnTypeProvider
{
    private const HYDRATE_OBJECT = 1;

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        $targets = [];
        foreach (['Doctrine\\ORM\\AbstractQuery', 'Doctrine\\ORM\\Query'] as $class) {
            foreach (['getResult', 'getOneOrNullResult', 'getSingleResult', 'toIterable', 'execute'] as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $parameters = $this->queryParameters($context->invocation->receiverType);
        if ($parameters === null) {
            return null;
        }
        [$keyType, $resultType] = $parameters;

        $method = strtolower($context->invocation->name);
        $hydration = match ($method) {
            'getresult', 'getoneornullresult', 'getsingleresult' => $context->invocation->getArgument(0, 'hydrationMode'),
            'toiterable', 'execute' => $context->invocation->getArgument(1, 'hydrationMode'),
            default => null,
        };

        // getResult() defaults to HYDRATE_OBJECT; the other methods use the query's (unknown) hydration mode.
        $objectHydration = $hydration === null
            ? $method === 'getresult'
            : $this->isObjectHydration($hydration->type, $hydration->expression ?? null);
        if (!$objectHydration) {
            return null;
        }

        $isList = (string) $keyType === 'int';

        return match ($method) {
            'getresult', 'execute' => $isList ? Type::list($resultType) : Type::array($keyType, $resultType),
            'getoneornullresult' => Type::union($resultType, Type::null()),
            'getsingleresult' => $resultType,
            'toiterable' => Type::namedObject('Traversable', $isList ? Type::int() : $keyType, $resultType),
            default => null,
        };
    }

    /** @return array{Type, Type}|null */
    private function queryParameters(?Type $receiverType): ?array
    {
        foreach ($receiverType?->atomicTypes ?? [] as $atomicType) {
            if (!$atomicType instanceof NamedObjectType || $atomicType->parameters === null) {
                continue;
            }

            $parameters = $atomicType->parameters;
            if (count($parameters) >= 2) {
                return [$parameters[0], $parameters[1]];
            }

            if (count($parameters) === 1) {
                return [Type::int(), $parameters[0]];
            }
        }

        return null;
    }

    private function isObjectHydration(?Type $type, ?string $expression): bool
    {
        if ($type?->getLiteralInt() === self::HYDRATE_OBJECT) {
            return true;
        }

        return $expression !== null && str_contains(strtoupper($expression), 'HYDRATE_OBJECT');
    }
}
