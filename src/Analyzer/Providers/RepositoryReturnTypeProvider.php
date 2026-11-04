<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Providers;

use Astrainer\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

final class RepositoryReturnTypeProvider implements MethodReturnTypeProvider
{
    public function __construct(private readonly EntityMetadataRepository $metadata) {}

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\Persistence\\ObjectManager', 'getRepository'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'getRepository'),
            MethodTarget::exact('Doctrine\\Persistence\\ManagerRegistry', 'getRepository'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $argument = $context->invocation->getArgument(0);
        $className = $argument?->type?->getLiteralClassString() ?? $argument?->type?->getLiteralString();
        if ($className === null) {
            return null;
        }

        $this->metadata->load($context->codebase);
        $entity = $this->metadata->getEntity($className);
        if ($entity === null) {
            return null;
        }

        if ($entity->repositoryClass !== 'Doctrine\\ORM\\EntityRepository') {
            return Type::namedObject($entity->repositoryClass);
        }

        return Type::namedObject('Doctrine\\ORM\\EntityRepository', Type::namedObject($entity->className));
    }
}