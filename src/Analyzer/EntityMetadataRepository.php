<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\DoctrinePropertyMetadata;
use Gimmickmedia\Mago\Doctrine\Analyzer\Metadata\EntityMetadata;
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
    private const ODM_MAPPING = 'Doctrine\\ODM\\MongoDB\\Mapping\\';

    /** @var list<string> */
    private readonly array $entityNamespaces;

    private ?Codebase $loadedCodebase = null;

    /** @var array<string, EntityMetadata> */
    private array $entities = [];

    /** @var array<string, string> repository class name => entity class name */
    private array $repositoryEntities = [];

    /**
     */
    public function __construct(private readonly DoctrineConfig $config)
    {
        $this->entityNamespaces = array_values(array_filter(
            $config->entityNamespaces,
            static fn (string $namespace): bool => $namespace !== '',
        ));
    }

    /**
     * Creates a repository from already known metadata (e.g. for tests or pre-computed mappings).
     *
     * @param list<EntityMetadata> $entities
     */
    public static function fromEntities(DoctrineConfig $config, array $entities): self
    {
        $repository = new self($config);
        foreach ($entities as $entity) {
            $repository->entities[$repository->normalizeName($entity->className)] = $entity;
            $defaultRepository = $entity->odm ? $config->odmRepositoryClass : $config->ormRepositoryClass;
            if (strcasecmp($entity->repositoryClass, $defaultRepository) !== 0) {
                $repository->repositoryEntities[$repository->normalizeName($entity->repositoryClass)] = $entity->className;
            }
        }

        return $repository;
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
        $mappedClasses = [];
        foreach ($classes as $className => $class) {
            $ormEntity = AttributeReader::find($class->attributes, self::ORM_MAPPING . 'Entity');
            $odmDocument = $this->findAttributeEndingWith($class->attributes, 'Document');
            if ($ormEntity === null && $odmDocument === null) {
                continue;
            }

            $propertyNames = [];
            foreach ($class->properties as $propertyName) {
                $propertyName = ltrim($propertyName, '$');
                $propertyNames[] = $propertyName;
                $propertyRequests[] = new MemberIdentifier($className, '$' . $propertyName);
            }

            $classProperties[$className] = $propertyNames;
            $mappedClasses[$className] = ['orm' => $ormEntity !== null, 'attribute' => $ormEntity ?? $odmDocument];
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
            $isOrm = $mappedClasses[$className]['orm'];
            $entityAttribute = $mappedClasses[$className]['attribute'];
            $customRepository = AttributeReader::stringArgument($entityAttribute, 0, 'repositoryClass');
            $repositoryClass = $customRepository ?? ($isOrm ? $this->config->ormRepositoryClass : $this->config->odmRepositoryClass);
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
                !$isOrm,
                AttributeReader::boolArgument($entityAttribute, 0, 'readOnly') ?? false,
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
        foreach ($receiverType?->atomicTypes ?? [] as $atomicType) {
            if (!$atomicType instanceof NamedObjectType) {
                continue;
            }

            $mapped = $this->repositoryEntities[$this->normalizeName($atomicType->name)] ?? null;
            if ($mapped !== null) {
                return $this->getEntity($mapped);
            }

            foreach ($atomicType->parameters ?? [] as $parameter) {
                $entityName = AttributeReader::classFromType($parameter);
                $entity = $entityName === null ? null : $this->getEntity($entityName);
                if ($entity !== null) {
                    return $entity;
                }
            }
        }

        $entityName = $this->repositoryEntities[$this->normalizeName($repositoryClass)] ?? null;

        return $entityName === null ? null : $this->getEntity($entityName);
    }

    public function isPropertyInitialized(string $className, string $propertyName): bool
    {
        $entity = $this->getEntity($className);

        return $entity?->readOnly || ($entity?->getProperty($propertyName)?->frameworkManaged ?? false);
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
        $column = AttributeReader::find($attributes, self::ORM_MAPPING . 'Column')
            ?? $this->findAttributeEndingWith($attributes, 'Field');
        $toOne = null;
        $toMany = false;
        foreach (['ManyToOne', 'OneToOne', 'OneToMany', 'ManyToMany', 'ReferenceOne', 'ReferenceMany', 'EmbedOne', 'EmbedMany'] as $relation) {
            $attribute = AttributeReader::find($attributes, self::ORM_MAPPING . $relation)
                ?? $this->findAttributeEndingWith($attributes, $relation);
            if ($attribute === null) {
                continue;
            }

            $toOne = $attribute;
            $toMany = in_array($relation, ['OneToMany', 'ManyToMany', 'ReferenceMany', 'EmbedMany'], true);
            break;
        }

        $embedded = AttributeReader::find($attributes, self::ORM_MAPPING . 'Embedded');
        $joinColumn = AttributeReader::find($attributes, self::ORM_MAPPING . 'JoinColumn')
            ?? $this->findAttributeEndingWith($attributes, 'ReferenceOne');
        $targetEntity = AttributeReader::stringArgument($toOne, 0, 'targetEntity')
            ?? AttributeReader::stringArgument($toOne, 0, 'targetDocument')
            ?? AttributeReader::stringArgument($embedded, 0, 'class')
            ?? AttributeReader::classFromType($property->declaredType?->type ?? $property->type?->type);
        $nullable = $column !== null
            ? (AttributeReader::boolArgument($column, 0, 'nullable') ?? false)
            : ($toOne !== null && !$toMany
                ? (AttributeReader::boolArgument($joinColumn, 0, 'nullable') ?? true)
                : false);
        $nativeType = $property->declaredType?->type ?? $property->type?->type;
        $identifier = AttributeReader::has($attributes, self::ORM_MAPPING . 'Id')
            || $this->findAttributeEndingWith($attributes, 'Id') !== null;
        $version = AttributeReader::has($attributes, self::ORM_MAPPING . 'Version');
        $generated = AttributeReader::has($attributes, self::ORM_MAPPING . 'GeneratedValue');
        $columnType = $column === null
            ? null
            : (AttributeReader::stringArgument($column, 0, 'type')
                ?? AttributeReader::stringArgument($column, 0, 'name')
                ?? 'string');
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

    /** @param list<\Mago\Sdk\Analyzer\Metadata\AttributeMetadata> $attributes */
    private function findAttributeEndingWith(array $attributes, string $name): ?\Mago\Sdk\Analyzer\Metadata\AttributeMetadata
    {
        foreach ($attributes as $attribute) {
            $attributeName = ltrim($attribute->name, '\\');
            if (
                str_ends_with($attributeName, '\\' . $name)
                && (str_starts_with($attributeName, self::ODM_MAPPING) || str_contains($attributeName, '\\Annotations\\'))
            ) {
                return $attribute;
            }
        }

        return null;
    }
}