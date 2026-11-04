<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Dql;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Throwable;

final class ObjectManagerDqlValidator
{
    private bool $loaded = false;

    private ?object $objectManager = null;

    public function __construct(private readonly DoctrineConfig $config) {}

    /** @return list<string> */
    public function validate(string $dql): array
    {
        $objectManager = $this->loadObjectManager();
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

    private function loadObjectManager(): ?object
    {
        if ($this->loaded) {
            return $this->objectManager;
        }
        $this->loaded = true;

        $loader = $this->config->objectManagerLoader;
        if ($loader === null) {
            return null;
        }

        $path = str_starts_with($loader, '/') ? $loader : getcwd() . '/' . $loader;
        if (!is_file($path)) {
            return null;
        }

        $manager = require $path;
        if (is_object($manager) && !method_exists($manager, 'createQuery') && method_exists($manager, 'getManager')) {
            $manager = $manager->getManager();
        }

        return $this->objectManager = is_object($manager) ? $manager : null;
    }
}