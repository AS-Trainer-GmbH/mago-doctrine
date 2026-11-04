<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine;

use Astrainer\Mago\Doctrine\Analyzer\DoctrinePlugin;
use Mago\Sdk\Extension;

final class DoctrineExtension
{
    /**
     * @param list<string> $entityNamespaces
     */
    public static function create(array $entityNamespaces = [], bool $reportFinalEntities = true): Extension
    {
        return new Extension(
            identifier: 'astrainer/doctrine',
            name: 'Doctrine ORM support',
            version: '1.0.0',
            analyzerPlugins: [new DoctrinePlugin($entityNamespaces, $reportFinalEntities)],
        );
    }
}