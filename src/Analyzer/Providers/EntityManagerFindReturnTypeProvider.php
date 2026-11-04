<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

final class EntityManagerFindReturnTypeProvider implements MethodReturnTypeProvider
{
    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        $targets = [];
        foreach (['Doctrine\\Persistence\\ObjectManager', 'Doctrine\\ORM\\EntityManagerInterface', 'Doctrine\\ODM\\MongoDB\\DocumentManager'] as $class) {
            foreach (['find', 'getReference', 'getPartialReference'] as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $argument = $context->invocation->getArgument(0, 'className', 'entityName');
        $className = $argument?->type?->getLiteralClassString();
        if ($className === null) {
            return null;
        }

        $entity = Type::namedObject($className);

        return strtolower($context->invocation->name) === 'find' ? Type::union($entity, Type::null()) : $entity;
    }
}
