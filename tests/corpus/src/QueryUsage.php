<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/** @return list<User> */
function dqlGetResult(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')->getResult();
}

/** @return list<User> */
function dqlGetResultWithObjectHydration(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')->getResult(Query::HYDRATE_OBJECT);
}

function dqlGetOneOrNullResult(EntityManagerInterface $manager): ?User
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')
        ->getOneOrNullResult(Query::HYDRATE_OBJECT);
}

function dqlGetSingleResult(EntityManagerInterface $manager): User
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')
        ->getSingleResult(Query::HYDRATE_OBJECT);
}

/** @return iterable<User> */
function dqlToIterable(EntityManagerInterface $manager): iterable
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')
        ->toIterable([], Query::HYDRATE_OBJECT);
}

/** @return list<User> */
function dqlExecute(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')
        ->execute(null, Query::HYDRATE_OBJECT);
}

/** @return list<array{id: int|null, email: string|null, name: string}> */
function dqlScalarFields(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u.id, u.email, u.name FROM Corpus\\Entity\\User u')->getResult();
}

/** @return array{id: int|null, email: string} */
function dqlCoalesce(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u.id, COALESCE(u.email, \'anonymous\') AS email FROM Corpus\\Entity\\User u')
        ->getSingleResult(Query::HYDRATE_OBJECT);
}

/** @return list<array{0: User, commentCount: int}> */
function dqlMixedResult(EntityManagerInterface $manager): array
{
    return $manager->createQuery(
        'SELECT u, COUNT(c.id) AS commentCount FROM Corpus\\Entity\\User u JOIN u.comments c GROUP BY u.id',
    )->getResult();
}

/** @return list<Group> */
function dqlJoinedAlias(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT g FROM Corpus\\Entity\\User u JOIN u.group g')->getResult();
}

/** @return array<int, User> */
function dqlIndexBy(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u INDEX BY u.id')->getResult();
}

/** @return list<int> */
function dqlIdentity(EntityManagerInterface $manager): array
{
    $rows = $manager->createQuery('SELECT IDENTITY(u.group) AS groupId FROM Corpus\\Entity\\User u')->getResult();

    return array_map(static fn (array $row): int => $row['groupId'], $rows);
}

/** @return list<User> */
function dqlQueryBuilder(EntityManagerInterface $manager): array
{
    return $manager->createQueryBuilder()
        ->select('u')
        ->from(User::class, 'u')
        ->where('u.email IS NOT NULL')
        ->getQuery()
        ->getResult();
}

/** @return list<User> */
function dqlNonChainedQueryBuilder(EntityManagerInterface $manager, string $email): array
{
    $queryBuilder = $manager->createQueryBuilder();
    $queryBuilder->select('u');
    $queryBuilder->from(User::class, 'u');
    $queryBuilder->andWhere($queryBuilder->expr()->eq('u.email', ':email'));
    $queryBuilder->setParameter('email', $email);

    return $queryBuilder->getQuery()->getResult();
}

function invalidDqlField(EntityManagerInterface $manager): mixed
{
    return $manager->createQuery('SELECT u.unknownField FROM Corpus\\Entity\\User u')->getResult();
}

function invalidDqlEntity(EntityManagerInterface $manager): mixed
{
    return $manager->createQuery('SELECT x FROM Corpus\\Entity\\Missing x')->getResult();
}

function invalidQueryBuilderField(EntityManagerInterface $manager): mixed
{
    return $manager->createQueryBuilder()
        ->select('u')
        ->from(User::class, 'u')
        ->where('u.nope = 1')
        ->getQuery()
        ->getResult();
}

/** @return list<Group> */
function wrongResultType(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u FROM Corpus\\Entity\\User u')->getResult();
}

/** @return list<array{id: string}> */
function wrongScalarType(EntityManagerInterface $manager): array
{
    return $manager->createQuery('SELECT u.id FROM Corpus\\Entity\\User u')->getResult();
}

/** @param class-string $entityClass */
function dynamicQueryBuilder(EntityManagerInterface $manager, string $entityClass): mixed
{
    return $manager->createQueryBuilder()
        ->select('e')
        ->from($entityClass, 'e')
        ->getQuery()
        ->getResult();
}

function nonLiteralDql(EntityManagerInterface $manager, string $dql): mixed
{
    return $manager->createQuery($dql)->getResult();
}

function nonLiteralSql(\Doctrine\DBAL\Connection $connection, string $table): mixed
{
    return $connection->executeQuery('SELECT * FROM ' . $table);
}
