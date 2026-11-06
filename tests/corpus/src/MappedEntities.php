<?php

declare(strict_types=1);

namespace Corpus\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractStep
{
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected Group $requiredGroup;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    protected Collection $comments;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    protected Collection $unparameterizedComments;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $payload = null;

    #[ORM\Column(type: 'string')]
    protected int $invalidInheritedColumn = 0;
}

#[ORM\Entity]
class ConcreteStep extends AbstractStep
{
}