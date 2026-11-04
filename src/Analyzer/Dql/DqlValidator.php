<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Dql;

use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;

final class DqlValidator
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    /** @return list<string> */
    public function validate(string $dql): array
    {
        $dql = (string) preg_replace('/\'(?:[^\']|\'\')*\'|"[^"]*"/', "''", $dql);
        if (!preg_match(
            '/^\s*(?:SELECT\b.*?\bFROM|UPDATE|DELETE(?:\s+FROM)?)\s+([\\\\\w]+)\s+(?:AS\s+)?([a-z_]\w*)/is',
            $dql,
            $from,
        )) {
            return ['DQL must contain a SELECT clause and a mapped entity in FROM.'];
        }

        $resolver = new QueryResultTypeResolver($this->metadata, $this->config);
        $entity = $resolver->findEntity($from[1]);
        if ($entity === null) {
            return [sprintf('Unknown Doctrine entity class %s in DQL FROM clause.', $from[1])];
        }

        $aliases = [strtolower($from[2]) => $entity];
        if (preg_match_all('/\bJOIN\s+([a-z_]\w*)\.([a-z_]\w*)\s+(?:AS\s+)?([a-z_]\w*)/i', $dql, $joins, PREG_SET_ORDER)) {
            foreach ($joins as $join) {
                $sourceEntity = $aliases[strtolower($join[1])] ?? null;
                $relation = $sourceEntity?->getProperty($join[2]);
                if ($sourceEntity === null || $relation === null || !$relation->mapped) {
                    return [sprintf('Unknown Doctrine association %s.%s in DQL JOIN clause.', $join[1], $join[2])];
                }

                if ($relation->targetEntity !== null) {
                    $target = $resolver->findEntity($relation->targetEntity);
                    if ($target !== null) {
                        $aliases[strtolower($join[3])] = $target;
                    }
                }
            }
        }

        $errors = [];
        if (preg_match_all('/\b([a-z_]\w*)\.([a-z_]\w*)\b/i', $dql, $paths, PREG_SET_ORDER)) {
            foreach ($paths as $path) {
                $alias = $aliases[strtolower($path[1])] ?? null;
                if ($alias !== null && $alias->getProperty($path[2]) === null) {
                    $errors[] = sprintf('Unknown Doctrine field %s.%s in DQL.', $path[1], $path[2]);
                }
            }
        }

        return array_values(array_unique($errors));
    }
}