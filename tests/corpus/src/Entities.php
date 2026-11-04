<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string')]
    public int $invalidColumn = 0;
}

/** @extends EntityRepository<User> */
final class UserRepository extends EntityRepository
{
}

#[ORM\Entity]
final class FinalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;
}