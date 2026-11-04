<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    public const FEATURE_ENABLED = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => self::FEATURE_ENABLED])]
    public bool $featureToggle = true;

    #[ORM\Column(type: 'boolean')]
    public bool $plainBoolean = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $constantDate = null;

    #[ORM\Column(type: Types::JSON)]
    public array $settings = [];

    #[ORM\Column]
    public bool $inferredBoolean = false;

    #[ORM\Column(nullable: true)]
    public ?int $inferredInteger = null;

    #[ORM\Column(type: 'string')]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    private array $invalidComments = [];

    public function __construct(Group $group)
    {
        $this->group = $group;
        $this->comments = new ArrayCollection();
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }
}

/** @extends EntityRepository<User> */
final class UserRepository extends EntityRepository
{
}

#[ORM\Entity]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $title = '';
}

#[ORM\Entity]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'comments')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(type: 'uuid7')]
    private string $uuid = '';

    final public function __construct()
    {
    }
}

#[ORM\Entity]
class UserGroup
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: User::class)]
        private readonly User $user,
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Group::class)]
        private readonly Group $group,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }
}

#[ORM\Entity]
final class FinalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;
}
