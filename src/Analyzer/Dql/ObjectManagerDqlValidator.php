<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Dql;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Gimmickmedia\Mago\Doctrine\Analyzer\ObjectManagerLoader;

final class ObjectManagerDqlValidator
{
    private readonly ObjectManagerLoader $objectManagerLoader;

    public function __construct(DoctrineConfig $config, ?ObjectManagerLoader $objectManagerLoader = null)
    {
        $this->objectManagerLoader = $objectManagerLoader ?? new ObjectManagerLoader($config);
    }

    /** @return list<string> */
    public function validate(string $dql): array
    {
        $objectManager = $this->objectManagerLoader->load();
        if ($objectManager === null) {
            return [];
        }

        try {
            if (!method_exists($objectManager, 'createQuery')) {
                return [];
            }

            $query = $objectManager->createQuery($dql);
            if (method_exists($query, 'parse')) {
                $query->parse();
            }
        } catch (Throwable $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }
}