<?php

declare(strict_types=1);

use Astrainer\Mago\Doctrine\DoctrineExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

(new Worker(DoctrineExtension::create(entityNamespaces: ['Corpus\\Entity'])))->run();