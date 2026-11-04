<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Dql;

use Gimmickmedia\Mago\Doctrine\Analyzer\DoctrineTypeMapper;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\DoctrinePropertyMetadata;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;

final class QueryResultTypeResolver
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    /**
     * Returns the generic parameters of `Doctrine\ORM\Query<TKey, TResult>`; `TKey` is `int` for list results.
     *
     * @return array{Type, Type}
     */
    public function resolveQueryParameters(string $dql): array
    {
        $result = $this->resolve($dql);
        if (!preg_match('/\bFROM\s+[\\\\\w]+\s+(?:AS\s+)?([a-z_]\w*)\s+INDEX\s+BY\s+([a-z_]\w*)\.([a-z_]\w*)/i', $dql, $index)) {
            return [Type::int(), $result];
        }

        $aliases = $this->aliases($dql);
        $keyType = $this->propertyType(($aliases[strtolower($index[2])] ?? null)?->getProperty($index[3]));

        return [$keyType, $result];
    }

    public function resolve(string $dql): Type
    {
        if (
            preg_match('/\(\s*SELECT\b/i', $dql)
            || !preg_match('/^\s*SELECT\s+(?:DISTINCT\s+)?(.*?)\s+FROM\s+[\\\\\w]+\s+(?:AS\s+)?[a-z_]\w*/is', $dql, $match)
        ) {
            return Type::mixed();
        }

        $aliases = $this->aliases($dql);
        if ($aliases === []) {
            return Type::mixed();
        }

        $expressions = $this->splitExpressions($match[1]);
        $entityItems = [];
        $scalarItems = [];
        foreach ($expressions as $index => $expression) {
            [$valueExpression, $resultAlias] = $this->splitAlias(trim($expression));
            $selectedEntity = preg_match('/^(?:PARTIAL\s+)?([a-z_]\w*)(?:\.\{.*\})?$/i', $valueExpression, $aliasMatch)
                ? ($aliases[strtolower($aliasMatch[1])] ?? null)
                : null;
            if ($selectedEntity !== null) {
                $entityItems[] = [$resultAlias, Type::namedObject($selectedEntity->className)];

                continue;
            }

            $scalarItems[] = new ArrayItem(
                new ArrayKey(ArrayKeyKind::String, $resultAlias ?? $this->defaultKey($valueExpression, $index)),
                false,
                $this->inferExpressionType($valueExpression, $aliases),
            );
        }

        if ($scalarItems === [] && count($entityItems) === 1 && $entityItems[0][0] === null) {
            return $entityItems[0][1];
        }

        $items = [];
        foreach ($entityItems as $position => [$resultAlias, $type]) {
            $items[] = new ArrayItem(
                $resultAlias === null ? new ArrayKey(ArrayKeyKind::Integer, $position) : new ArrayKey(ArrayKeyKind::String, $resultAlias),
                false,
                $type,
            );
        }

        return Type::fromAtomic(new KeyedArrayType([...$items, ...$scalarItems], null, null, true));
    }

    /** @return array<string, EntityMetadata> */
    private function aliases(string $dql): array
    {
        $aliases = [];
        if (!preg_match_all('/(?:\bFROM|,)\s+([\\\\\w]+)\s+(?:AS\s+)?([a-z_]\w*)/i', (string) preg_replace('/\bWHERE\b.*$/is', '', $dql), $roots, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($roots as $root) {
            $rootEntity = $this->findEntity($root[1]);
            if ($rootEntity !== null) {
                $aliases[strtolower($root[2])] = $rootEntity;
            }
        }

        if (preg_match_all('/\bJOIN\s+([a-z_]\w*)\.([a-z_]\w*)\s+(?:AS\s+)?([a-z_]\w*)/i', $dql, $joins, PREG_SET_ORDER)) {
            foreach ($joins as $join) {
                $sourceEntity = $aliases[strtolower($join[1])] ?? null;
                $relation = $sourceEntity?->getProperty($join[2]);
                $targetEntity = $relation?->targetEntity === null ? null : $this->findEntity($relation->targetEntity);
                if ($targetEntity !== null) {
                    $aliases[strtolower($join[3])] = $targetEntity;
                }
            }
        }

        if (preg_match_all('/\bJOIN\s+([\\\\\w]+)\s+(?:AS\s+)?([a-z_]\w*)\s+WITH\b/i', $dql, $arbitraryJoins, PREG_SET_ORDER)) {
            foreach ($arbitraryJoins as $join) {
                $targetEntity = $this->findEntity($join[1]);
                if ($targetEntity !== null) {
                    $aliases[strtolower($join[2])] = $targetEntity;
                }
            }
        }

        return $aliases;
    }

    /** @param array<string, EntityMetadata> $aliases */
    private function inferExpressionType(string $expression, array $aliases): Type
    {
        $expression = trim($expression);
        if (preg_match('/^NEW\s+([\\\\\w]+)/i', $expression, $newObject)) {
            return Type::namedObject($newObject[1]);
        }

        if (preg_match('/^([a-z_]\w*)\.([a-z_]\w*)$/i', $expression, $path)) {
            $property = ($aliases[strtolower($path[1])] ?? null)?->getProperty($path[2]);

            return $this->propertyType($property);
        }

        if (preg_match('/^\'(?:[^\']|\'\')*\'$|^"[^"]*"$/', $expression)) {
            return Type::string();
        }

        if (preg_match('/^-?\d+$/', $expression)) {
            return Type::int();
        }

        if (preg_match('/^-?\d+\.\d+$/', $expression)) {
            return Type::float();
        }

        if (preg_match('/^(TRUE|FALSE)$/i', $expression)) {
            return Type::bool();
        }

        if (preg_match('/^\(\s*(.*)\s*\)$/s', $expression, $parenthesized)) {
            return $this->inferExpressionType($parenthesized[1], $aliases);
        }

        if (preg_match('/^CASE\b(.*)\bEND$/is', $expression, $case)) {
            $types = [];
            if (preg_match_all('/\b(?:THEN|ELSE)\s+(.+?)(?=\s+(?:WHEN|ELSE|END)\b|$)/is', $case[1] . ' END', $branches)) {
                foreach ($branches[1] as $branch) {
                    $types[] = $this->inferExpressionType($branch, $aliases);
                }
            }
            if (!preg_match('/\bELSE\b/i', $case[1])) {
                $types[] = Type::null();
            }

            return $this->unionOf($types);
        }

        if (preg_match('/^[^()]+\s*[-+*\/]\s*[^()]+$/', $expression)) {
            return Type::union(Type::int(), Type::float(), Type::string());
        }

        if (!preg_match('/^([a-z_]\w*)\s*\((.*)\)$/is', $expression, $function)) {
            return Type::mixed();
        }

        $name = strtoupper($function[1]);
        if (isset($this->config->customFunctions[$name])) {
            return $this->config->customFunctions[$name];
        }

        $arguments = $this->splitExpressions($function[2]);
        $argumentType = isset($arguments[0]) ? $this->inferExpressionType($arguments[0], $aliases) : Type::mixed();

        return match ($name) {
            'COUNT', 'LENGTH', 'SIZE', 'LOCATE', 'BIT_AND', 'BIT_OR', 'DATE_DIFF' => Type::int(),
            'AVG', 'SQRT' => Type::union(Type::float(), Type::string(), Type::null()),
            'SUM', 'MIN', 'MAX' => $this->nullableAggregate($argumentType),
            'COALESCE' => $this->coalesce($arguments, $aliases),
            'NULLIF' => Type::union($argumentType, Type::null()),
            'IDENTITY' => $this->identity($arguments[0] ?? '', $aliases),
            'LOWER', 'UPPER', 'CONCAT', 'SUBSTRING', 'TRIM', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'DATE_ADD', 'DATE_SUB' => Type::string(),
            'ABS', 'MOD' => $argumentType,
            default => Type::mixed(),
        };
    }

    private function nullableAggregate(Type $type): Type
    {
        foreach ($type->atomicTypes as $atomicType) {
            if ((string) $atomicType === 'null') {
                return $type;
            }
        }

        return Type::union($type, Type::null());
    }

    /**
     * @param list<string> $arguments
     * @param array<string, EntityMetadata> $aliases
     */
    private function coalesce(array $arguments, array $aliases): Type
    {
        $types = [];
        foreach ($arguments as $argument) {
            $type = $this->inferExpressionType($argument, $aliases);
            $nonNull = [];
            $nullable = false;
            foreach ($type->atomicTypes as $atomicType) {
                if ((string) $atomicType === 'null') {
                    $nullable = true;
                } else {
                    $nonNull[] = $atomicType;
                }
            }
            if ($nonNull !== []) {
                $types[] = $nullable ? Type::fromAtomics(...$nonNull) : $type;
            }
            if (!$nullable && $nonNull !== []) {
                return $this->unionOf($types);
            }
        }

        $types[] = Type::null();

        return $this->unionOf($types);
    }

    /** @param list<Type> $types */
    private function unionOf(array $types): Type
    {
        $unique = [];
        foreach ($types as $type) {
            $unique[(string) $type] ??= $type;
        }
        $unique = array_values($unique);

        return match (count($unique)) {
            0 => Type::mixed(),
            1 => $unique[0],
            default => Type::union(...$unique),
        };
    }

    /** @param array<string, EntityMetadata> $aliases */
    private function identity(string $expression, array $aliases): Type
    {
        if (!preg_match('/^([a-z_]\w*)\.([a-z_]\w*)/i', trim($expression), $path)) {
            return Type::mixed();
        }

        $relation = ($aliases[strtolower($path[1])] ?? null)?->getProperty($path[2]);
        $target = $relation?->targetEntity === null ? null : $this->findEntity($relation->targetEntity);
        if ($relation === null || $target === null) {
            return Type::mixed();
        }

        foreach ($target->properties as $property) {
            if ($property->identifier) {
                $type = $this->propertyType($property);

                return $relation->nullable ? Type::union($type, Type::null()) : $type;
            }
        }

        return Type::mixed();
    }

    private function propertyType(?DoctrinePropertyMetadata $property): Type
    {
        if ($property === null) {
            return Type::mixed();
        }

        if ($property->targetEntity !== null) {
            return Type::namedObject($property->targetEntity);
        }

        $type = $property->columnType === null
            ? $property->nativeType
            : DoctrineTypeMapper::toPhpType($property->columnType, $property->enumType, $this->config->customTypes);

        if ($type === null) {
            return Type::mixed();
        }

        return DoctrineTypeMapper::nullable($type, $property->nullable);
    }

    public function findEntity(string $className): ?EntityMetadata
    {
        $entity = $this->metadata->getEntity(ltrim($className, '\\'));
        if ($entity !== null) {
            return $entity;
        }

        foreach ($this->metadata->getEntities() as $candidate) {
            $separator = strrpos($candidate->className, '\\');
            $shortName = $separator === false ? $candidate->className : substr($candidate->className, $separator + 1);
            if (strcasecmp($shortName, $className) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function splitExpressions(string $expressions): array
    {
        $result = [];
        $depth = 0;
        $quote = null;
        $start = 0;
        $length = strlen($expressions);

        for ($index = 0; $index < $length; $index++) {
            $character = $expressions[$index];
            if ($quote !== null) {
                if ($character === $quote && ($index === 0 || $expressions[$index - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $result[] = trim(substr($expressions, $start, $index - $start));
                $start = $index + 1;
            }
        }

        $last = trim(substr($expressions, $start));
        if ($last !== '') {
            $result[] = $last;
        }

        return $result;
    }

    /** @return array{string, ?string} */
    private function splitAlias(string $expression): array
    {
        if (preg_match('/^(.*?)\s+AS\s+([a-z_]\w*)$/i', $expression, $match)) {
            return [trim($match[1]), $match[2]];
        }

        return [$expression, null];
    }

    private function defaultKey(string $expression, int $index): string
    {
        if (preg_match('/^[a-z_]\w*\.([a-z_]\w*)$/i', $expression, $property)) {
            return $property[1];
        }

        return 'sclr' . $index;
    }
}