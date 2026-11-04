<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Dql;

use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

final class QueryBuilderState
{
    private const QUERY_BUILDER = 'Doctrine\\ORM\\QueryBuilder';

    private const TYPE_NEUTRAL_METHODS = [
        'where', 'andwhere', 'orwhere', 'groupby', 'addgroupby', 'orderby', 'addorderby',
    ];

    public static function initialType(): Type
    {
        return Type::namedObject(self::QUERY_BUILDER, Type::literalString('[]'));
    }

    public static function appendInvocation(Invocation $invocation): Type
    {
        $arguments = [];
        foreach ($invocation->arguments as $argument) {
            $value = $argument->type?->getLiteralClassString() ?? $argument->type?->getLiteralString();
            $value ??= match ($argument->type?->getLiteralBool()) {
                true => '1',
                false => '0',
                null => null,
            };
            if ($value === null) {
                if (in_array(strtolower($invocation->name), self::TYPE_NEUTRAL_METHODS, true)) {
                    // Dynamic conditions/ordering do not change the result type; keep the known state.
                    return self::append($invocation->receiverType, 'ignored', []);
                }

                return Type::namedObject(self::QUERY_BUILDER, Type::mixed());
            }
            $arguments[] = $value;
        }

        return self::append($invocation->receiverType, strtolower($invocation->name), $arguments);
    }

    /** @param list<string> $arguments */
    public static function append(?Type $receiverType, string $method, array $arguments): Type
    {
        $operations = self::operations($receiverType);
        if ($operations === null) {
            return Type::namedObject(self::QUERY_BUILDER, Type::mixed());
        }

        $operations[] = ['method' => $method, 'arguments' => $arguments];

        return Type::namedObject(self::QUERY_BUILDER, Type::literalString(json_encode($operations, JSON_THROW_ON_ERROR)));
    }

    public static function dql(?Type $receiverType): ?string
    {
        $operations = self::operations($receiverType);
        if ($operations === null) {
            return null;
        }

        $select = [];
        $from = [];
        $joins = [];
        $where = [];
        $groupBy = [];
        $orderBy = [];
        $distinct = false;
        foreach ($operations as $operation) {
            $arguments = $operation['arguments'];
            $first = $arguments[0] ?? null;
            if ($first === null && $operation['method'] !== 'distinct') {
                continue;
            }

            switch ($operation['method']) {
                case 'select':
                    $select = $arguments;
                    break;
                case 'addselect':
                    array_push($select, ...$arguments);
                    break;
                case 'distinct':
                    $distinct = true;
                    break;
                case 'from':
                    if (isset($arguments[1])) {
                        $from[] = $first . ' ' . $arguments[1] . (isset($arguments[2]) ? ' INDEX BY ' . $arguments[2] : '');
                    }
                    break;
                case 'indexby':
                    if (isset($arguments[1]) && $from !== []) {
                        $from[count($from) - 1] .= ' INDEX BY ' . $arguments[1];
                    }
                    break;
                case 'join':
                case 'innerjoin':
                case 'leftjoin':
                    if (isset($arguments[1], $arguments[2])) {
                        $kind = $operation['method'] === 'leftjoin' ? 'LEFT JOIN' : 'JOIN';
                        $joins[] = $kind . ' ' . $arguments[0] . '.' . $arguments[1] . ' ' . $arguments[2];
                    }
                    break;
                case 'where':
                case 'andwhere':
                case 'orwhere':
                    $where[] = $first;
                    break;
                case 'groupby':
                case 'addgroupby':
                    $groupBy[] = $first;
                    break;
                case 'orderby':
                case 'addorderby':
                    $orderBy[] = $first . (isset($arguments[1]) ? ' ' . $arguments[1] : '');
                    break;
            }
        }

        if ($from === []) {
            return null;
        }

        $rootAlias = preg_split('/\s+/', $from[0])[1] ?? '';
        $select = $select === [] ? [$rootAlias] : $select;
        $dql = 'SELECT ' . ($distinct ? 'DISTINCT ' : '') . implode(', ', $select) . ' FROM ' . implode(', ', $from);
        if ($joins !== []) {
            $dql .= ' ' . implode(' ', $joins);
        }
        if ($where !== []) {
            $dql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($groupBy !== []) {
            $dql .= ' GROUP BY ' . implode(', ', $groupBy);
        }
        if ($orderBy !== []) {
            $dql .= ' ORDER BY ' . implode(', ', $orderBy);
        }

        return $dql;
    }

    /**
     * @return list<array{method: string, arguments: list<string>}>|null
     */
    private static function operations(?Type $receiverType): ?array
    {
        foreach ($receiverType?->atomicTypes ?? [] as $atomicType) {
            if (!$atomicType instanceof NamedObjectType || strcasecmp($atomicType->name, self::QUERY_BUILDER) !== 0) {
                continue;
            }

            $serialized = $atomicType->parameters[0]?->getLiteralString() ?? null;
            if ($serialized === null) {
                return null;
            }

            $operations = json_decode($serialized, true);
            if (!is_array($operations)) {
                return null;
            }

            return $operations;
        }

        return null;
    }
}
