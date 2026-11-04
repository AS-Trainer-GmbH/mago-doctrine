<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer\Providers;

use Astrainer\Mago\Doctrine\Analyzer\DoctrineTypeMapper;
use Astrainer\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

final class MagicRepositoryMethodsProvider implements MethodReturnTypeProvider, CallableSignatureProvider
{
    public function __construct(private readonly EntityMetadataRepository $metadata) {}

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\ORM\\EntityRepository', 'findBy*'),
            MethodTarget::exact('Doctrine\\ORM\\EntityRepository', 'findOneBy*'),
            MethodTarget::exact('Doctrine\\ORM\\EntityRepository', 'countBy*'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $this->metadata->load($context->codebase);
        $invocation = $context->invocation;
        $property = $this->metadata->getMagicMethodProperty(
            $invocation->declaringClass,
            $invocation->name,
            $invocation->receiverType,
        );
        if ($property === null) {
            return null;
        }

        $entity = $this->metadata->getEntityForRepository($invocation->declaringClass, $invocation->receiverType);
        if ($entity === null) {
            return null;
        }

        $method = strtolower($invocation->name);
        if (str_starts_with($method, 'findoneby')) {
            return Type::union(Type::namedObject($entity->className), Type::null());
        }

        if (str_starts_with($method, 'countby')) {
            return Type::int();
        }

        return Type::list(Type::namedObject($entity->className));
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $this->metadata->load($context->codebase);
        $invocation = $context->invocation;
        $property = $this->metadata->getMagicMethodProperty(
            $invocation->declaringClass,
            $invocation->name,
            $invocation->receiverType,
        );
        if ($property === null) {
            return null;
        }

        $valueType = $property->nativeType;
        if ($valueType === null && $property->columnType !== null) {
            $valueType = DoctrineTypeMapper::toPhpType($property->columnType, $property->enumType);
        }
        $valueType = $valueType === null
            ? Type::mixed()
            : DoctrineTypeMapper::nullable($valueType, $property->nullable);
        $method = strtolower($invocation->name);
        $parameters = [new CallableParameter(name: '$value', type: $valueType)];

        if (str_starts_with($method, 'findby')) {
            $parameters[] = new CallableParameter(
                name: '$orderBy',
                type: Type::array(Type::string(), Type::mixed()),
                hasDefault: true,
            );
            $parameters[] = new CallableParameter(name: '$limit', type: Type::int(), hasDefault: true);
            $parameters[] = new CallableParameter(name: '$offset', type: Type::int(), hasDefault: true);
        } elseif (str_starts_with($method, 'findoneby')) {
            $parameters[] = new CallableParameter(
                name: '$orderBy',
                type: Type::array(Type::string(), Type::mixed()),
                hasDefault: true,
            );
        }

        return new EffectiveCallableSignature($parameters, displayName: $invocation->name);
    }
}