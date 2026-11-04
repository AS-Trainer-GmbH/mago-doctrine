<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

final class RepositoryReturnTypeProvider implements MethodReturnTypeProvider
{
    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        private readonly DoctrineConfig $config,
    ) {}

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\Persistence\\ObjectManager', 'getRepository'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'getRepository'),
            MethodTarget::exact('Doctrine\\Persistence\\ManagerRegistry', 'getRepository'),
            MethodTarget::exact('Doctrine\\ODM\\MongoDB\\DocumentManager', 'getRepository'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $argument = $context->invocation->getArgument(0, 'className', 'persistentObject');
        $className = $argument?->type?->getLiteralClassString() ?? $argument?->type?->getLiteralString();
        if ($className === null) {
            return null;
        }

        $this->metadata->load($context->codebase);
        $entity = $this->metadata->getEntity($className);
        if ($entity === null) {
            return null;
        }

        $defaultRepository = $entity->odm ? $this->config->odmRepositoryClass : $this->config->ormRepositoryClass;
        if (strcasecmp($entity->repositoryClass, $defaultRepository) !== 0) {
            return Type::namedObject($entity->repositoryClass);
        }

        return Type::namedObject($defaultRepository, Type::namedObject($entity->className));
    }
}
