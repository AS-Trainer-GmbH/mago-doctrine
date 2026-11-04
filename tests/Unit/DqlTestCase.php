<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\EntityMetadataRepository;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\DoctrinePropertyMetadata;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Mago\Sdk\Analyzer\Type;
use PHPUnit\Framework\TestCase;

abstract class DqlTestCase extends TestCase
{
    protected function config(): DoctrineConfig
    {
        return new DoctrineConfig(customFunctions: ['FLOOR' => Type::int()]);
    }

    protected function metadata(): EntityMetadataRepository
    {
        return EntityMetadataRepository::fromEntities($this->config(), [
            new EntityMetadata('Acme\\User', 'Acme\\UserRepository', [
                'id' => $this->column('id', 'integer', identifier: true),
                'email' => $this->column('email', 'string'),
                'name' => $this->column('name', 'string', nullable: true),
                'group' => $this->relation('group', 'Acme\\Group', nullable: true),
            ], false, false, false, null, null),
            new EntityMetadata('Acme\\Group', 'Doctrine\\ORM\\EntityRepository', [
                'id' => $this->column('id', 'integer', identifier: true),
                'title' => $this->column('title', 'string'),
            ], false, false, false, null, null),
        ]);
    }

    private function column(string $name, string $type, bool $nullable = false, bool $identifier = false): DoctrinePropertyMetadata
    {
        return new DoctrinePropertyMetadata($name, true, $type, null, $nullable, null, false, $identifier, $identifier, false, $identifier, null, null);
    }

    private function relation(string $name, string $target, bool $nullable): DoctrinePropertyMetadata
    {
        return new DoctrinePropertyMetadata($name, true, null, null, $nullable, $target, false, false, false, false, false, null, null);
    }
}
