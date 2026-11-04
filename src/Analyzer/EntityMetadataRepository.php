<?php

declare(strict_types=1);

namespace Astrainer\Mago\Doctrine\Analyzer;

use Astrainer\Mago\Doctrine\Analyzer\Metadata\DoctrinePropertyMetadata;
use Astrainer\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function array_chunk;
use function array_filter;
use function array_values;
use function strtolower;
use function str_starts_with;
use function str_ends_with;

final class EntityMetadataRepository
{
    private const ORM_MAPPING = 'Doctrine\\ORM\\Mapping\\';

    /** @var list<string> */
    private readonly array $entityNamespaces;

    private ?Codebase $loadedCodebase = null;

    /** @var array<string, EntityMetadata> */
    private array $entities = [];

    /** @var array<string, string> repository class name => entity class name */
    private array $repositoryEntities = [];

    /**
     * @param list<string> $entityNamespaces
     */
    public function __construct(array $entityNamespaces = [])
    {
        $this->entityNamespaces = array_values(array_filter(
            $entityNamespaces,
            static fn (string $namespace): bool => $namespace !== '',
        ));
    }

    public function load(Codebase $codebase): void
    {
        if ($this->loadedCodebase === $codebase) {
            return;
        }

        $this->loadedCodebase = $codebase;
        $this->entities = [];
        $this->repositoryEntities = [];

        $classNames = array_values(array_filter(
            $codebase->getClassNames(),
            fn (string $className): bool => $this->matchesNamespace($className),
        ));
        $classes = [];
        foreach (array_chunk($classNames, 128) as $classBatch) {
            foreach ($codebase->getMultipleClasses($classBatch) as $class) {
                if ($class !== null) {
                    $classes[$class->name] = $class;
                }
            }
        }

        $propertyRequests = [];
        $classProperties = [];
        foreach ($classes as $className => $class) {
            if (!AttributeReader::has($class->attributes, self::ORM_MAPPING . 'Entity')) {
                continue;
            }

            $propertyNames = [];
            foreach ($class->properties as $propertyName) {
                $propertyName = ltrim($propertyName, '$');
                $propertyNames[] = $propertyName;
                $propertyRequests[] = new MemberIdentifier($className, '$' . $propertyName);
            }

            $classProperties[$className] = $propertyNames;
        }

        $properties = [];
        foreach (array_chunk($propertyRequests, 128) as $propertyBatch) {
            foreach ($codebase->getMultipleProperties($propertyBatch) as $index => $property) {
                $request = $propertyBatch[$index];
                if ($property !== null) {
                    $properties[$request->class][ltrim($request->member, '$')] = $property;
                }
            }
        }

        foreach ($classProperties as $className => $propertyNames) {
            $class = $classes[$className];
            $entityAttribute = AttributeReader::find($class->attributes, self::ORM_MAPPING . 'Entity');
            $customRepository = AttributeReader::stringArgument($entityAttribute, 0, 'repositoryClass');
            $repositoryClass = $customRepository ?? 'Doctrine\\ORM\\EntityRepository';
            $mappedProperties = [];
            foreach ($propertyNames as $propertyName) {
                $property = $properties[$className][$propertyName] ?? null;
                if ($property === null) {
                    continue;
                }

                $mappedProperties[$propertyName] = $this->createPropertyMetadata($propertyName, $property);
            }

            $entity = new EntityMetadata(
                $class->originalName,
                $repositoryClass,
                $mappedProperties,
                $class->flags->contains(MetadataFlags::FINAL),
                $class->location,
                $class->nameLocation,
            );
            $this->entities[$this->normalizeName($class->originalName)] = $entity;
            if ($customRepository !== null) {
                $this->repositoryEntities[$this->normalizeName($repositoryClass)] = $class->originalName;
            }
        }
    }

    /** @return list<EntityMetadata> */
    public function getEntities(): array
    {
        return array_values($this->entities);
    }

    public function getEntity(string $className): ?EntityMetadata
    {
        return $this->entities[$this->normalizeName($className)] ?? null;
    }

    public function getEntityForRepository(string $repositoryClass, ?Type $receiverType = null): ?EntityMetadata
    {
        $entityName = $this->repositoryEntities[$this->normalizeName($repositoryClass)] ?? null;
        if ($entityName === null && $receiverType !== null) {
            foreach ($receiverType->atomicTypes as $atomicType) {
                if (!$atomicType instanceof NamedObjectType || $atomicType->parameters === null) {
                    continue;
                }

                foreach ($atomicType->parameters as $parameter) {
                    $entityName = AttributeReader::classFromType($parameter);
                    if ($entityName !== null && $this->getEntity($entityName) !== null) {
                        break 2;
                    }
                }
            }
        }

        return $entityName === null ? null : $this->getEntity($entityName);
    }

    public function isPropertyInitialized(string $className, string $propertyName): bool
    {
        $entity = $this->getEntity($className);

        return $entity?->getProperty($propertyName)?->frameworkManaged ?? false;
    }

    public function getMagicMethodProperty(
        string $repositoryClass,
        string $method,
        ?Type $receiverType = null,
    ): ?DoctrinePropertyMetadata
    {
        $entity = $this->getEntityForRepository($repositoryClass, $receiverType);
        if ($entity === null) {
            return null;
        }

        foreach (['findBy', 'findOneBy', 'countBy'] as $prefix) {
            if (!str_starts_with(strtolower($method), strtolower($prefix))) {
                continue;
            }

            $propertyName = lcfirst(substr($method, strlen($prefix)));

            $property = $propertyName === '' ? null : $entity->getProperty($propertyName);

            return $property?->mapped ? $property : null;
        }

        return null;
    }

    private function matchesNamespace(string $className): bool
    {
        if ($this->entityNamespaces === []) {
            return true;
        }

        foreach ($this->entityNamespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            if ($className === $namespace || str_starts_with($className, $namespace . '\\')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }

    private function createPropertyMetadata(string $name, PropertyMetadata $property): DoctrinePropertyMetadata
    {
        $attributes = $property->attributes;
        $column = AttributeReader::find($attributes, self::ORM_MAPPING . 'Column');
        $toOne = null;
        $toMany = false;
        foreach (['ManyToOne', 'OneToOne', 'OneToMany', 'ManyToMany'] as $relation) {
            $attribute = AttributeReader::find($attributes, self::ORM_MAPPING . $relation);
            if ($attribute === null) {
                continue;
            }

            $toOne = $attribute;
            $toMany = $relation === 'OneToMany' || $relation === 'ManyToMany';
            break;
        }

        $embedded = AttributeReader::find($attributes, self::ORM_MAPPING . 'Embedded');
        $joinColumn = AttributeReader::find($attributes, self::ORM_MAPPING . 'JoinColumn');
        $targetEntity = AttributeReader::stringArgument($toOne, 0, 'targetEntity')
            ?? AttributeReader::stringArgument($embedded, 0, 'class')
            ?? AttributeReader::classFromType($property->declaredType?->type ?? $property->type?->type);
        $nullable = $column !== null
            ? (AttributeReader::boolArgument($column, 0, 'nullable') ?? false)
            : ($toOne !== null && !$toMany
                ? (AttributeReader::boolArgument($joinColumn, 0, 'nullable') ?? true)
                : false);
        $nativeType = $property->declaredType?->type ?? $property->type?->type;
        $identifier = AttributeReader::has($attributes, self::ORM_MAPPING . 'Id');
        $version = AttributeReader::has($attributes, self::ORM_MAPPING . 'Version');
        $generated = AttributeReader::has($attributes, self::ORM_MAPPING . 'GeneratedValue');
        $columnType = $column === null ? null : (AttributeReader::stringArgument($column, 0, 'type') ?? 'string');
        $enumType = AttributeReader::stringArgument($column, 0, 'enumType');
        $frameworkManaged = $identifier || $version || $this->hasManagedAttribute($attributes);

        return new DoctrinePropertyMetadata(
            $name,
            $column !== null || $toOne !== null || $embedded !== null,
            $columnType,
            $enumType,
            $nullable,
            $targetEntity,
            $toMany,
            $identifier,
            $generated,
            $version,
            $frameworkManaged,
            $nativeType,
            $property->location ?? $column?->location ?? $toOne?->location ?? $embedded?->location,
        );
    }

    /**
     * @param list<\Mago\Sdk\Analyzer\Metadata\AttributeMetadata> $attributes
     */
    private function hasManagedAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            foreach (['Timestampable', 'Blameable', 'Slug', 'TreeLeft', 'TreeRight', 'TreeLevel', 'TreeRoot', 'IpTraceable'] as $managedName) {
                if (str_ends_with($attribute->name, '\\' . $managedName)) {
                    return true;
                }
            }
        }

        return false;
    }
}