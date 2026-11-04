<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ManagerRegistry;

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