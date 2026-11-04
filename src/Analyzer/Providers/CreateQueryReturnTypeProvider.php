<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\QueryResultTypeResolver;
use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

final class CreateQueryReturnTypeProvider implements MethodReturnTypeProvider
{
    private readonly QueryResultTypeResolver $resolver;

    public function __construct(
        private readonly EntityMetadataRepository $metadata,
        DoctrineConfig $config,
    ) {
        $this->resolver = new QueryResultTypeResolver($metadata, $config);
    }

    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'createQuery'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManager', 'createQuery'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $dql = $context->invocation->getArgument(0)?->type?->getLiteralString();
        if ($dql === null) {
            return null;
        }

        $this->metadata->load($context->codebase);

        return Type::namedObject('Doctrine\\ORM\\Query', ...$this->resolver->resolveQueryParameters($dql));
    }
}