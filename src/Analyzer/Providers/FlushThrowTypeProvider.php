<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\MethodThrowTypeProvider;
use Mago\Sdk\Analyzer\ThrowTypeProviderContext;

final class FlushThrowTypeProvider implements MethodThrowTypeProvider
{
    /** @return non-empty-list<MethodTarget> */
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Doctrine\\ORM\\EntityManagerInterface', 'flush'),
            MethodTarget::exact('Doctrine\\ORM\\EntityManager', 'flush'),
        ];
    }

    /** @return list<non-empty-string> */
    public function getThrownExceptions(ThrowTypeProviderContext $context): array
    {
        return [
            'Doctrine\\ORM\\Exception\\ORMException',
            'Doctrine\\DBAL\\Exception\\UniqueConstraintViolationException',
        ];
    }
}
