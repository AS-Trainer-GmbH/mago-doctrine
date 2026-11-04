<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;

/**
 * Adds the `matching()` method missing on `Doctrine\Common\Collections\Collection`
 * (all ORM collections implement `Selectable`).
 */
final class CollectionMatchingIssueFilter implements IssueFilterHook
{
    /** @return non-empty-list<non-empty-string> */
    public function getCodes(): array
    {
        return ['non-existent-method', 'possibly-non-existent-method'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $message = strtolower($context->issue->message);

        return str_contains($message, 'matching') && str_contains($message, 'doctrine\\common\\collections\\')
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }
}
