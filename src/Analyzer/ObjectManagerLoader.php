<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Throwable;

final class ObjectManagerLoader
{
    private bool $loaded = false;

    private ?object $objectManager = null;

    public function __construct(private readonly DoctrineConfig $config) {}

    public function load(): ?object
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

        try {
            $manager = require $path;
            if (is_object($manager) && !method_exists($manager, 'createQuery') && method_exists($manager, 'getManager')) {
                $manager = $manager->getManager();
            }
        } catch (Throwable) {
            return null;
        }

        return $this->objectManager = is_object($manager) ? $manager : null;
    }
}