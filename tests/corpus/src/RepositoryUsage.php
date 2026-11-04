<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

/** @return list<User> */
function findUsers(ObjectManager $manager): array
{
    $repository = $manager->getRepository(User::class);

    return $repository->findByEmail('person@example.com');
}

function findOneUser(ManagerRegistry $registry): ?User
{
    return $registry->getRepository(User::class)->findOneByEmail('person@example.com');
}

function countUsers(EntityManagerInterface $manager): int
{
    return $manager->getRepository(User::class)->countByEmail('person@example.com');
}

/** @return EntityRepository<Group> */
function genericRepository(EntityManagerInterface $manager): EntityRepository
{
    return $manager->getRepository(Group::class);
}

function findUser(EntityManagerInterface $manager): ?User
{
    return $manager->find(User::class, 1);
}

function userReference(EntityManagerInterface $manager): User
{
    return $manager->getReference(User::class, 1);
}

/** @return list<User> */
function unknownCriteriaField(EntityManagerInterface $manager): array
{
    return $manager->getRepository(User::class)->findBy(['nope' => 1]);
}

function knownCriteriaField(EntityManagerInterface $manager): ?User
{
    return $manager->getRepository(User::class)->findOneBy(['email' => 'person@example.com']);
}

function firstComment(User $user): ?Comment
{
    $comments = $user->getComments();
    if ($comments->isEmpty()) {
        return null;
    }

    return $comments->first();
}

function lastComment(User $user): Comment
{
    $comments = $user->getComments();
    if (!$comments->isEmpty()) {
        return $comments->last();
    }

    throw new \RuntimeException('No comments.');
}

function proxyName(): string
{
    return \Proxies\__CG__\Corpus\Entity\User::class;
}

/** @return \Doctrine\Common\Collections\ReadableCollection<int, Comment> */
function matchingComments(User $user): \Doctrine\Common\Collections\ReadableCollection
{
    return $user->getComments()->matching(\Doctrine\Common\Collections\Criteria::create());
}
