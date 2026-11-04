<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Hooks;

use Mago\Sdk\Analyzer\AfterFileAnalysisContext;
use Mago\Sdk\Analyzer\AfterFileAnalysisHook;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

/**
 * Forbids direct use of generated Doctrine proxy class names (`Proxies\__CG__\...`).
 */
final class ProxyClassNameHook implements AfterFileAnalysisHook
{
    /** @return list<FileAnalysisRequirement> */
    public function getRequirements(): array
    {
        return [];
    }

    public function afterFileAnalysis(AfterFileAnalysisContext $context): void
    {
        $source = $context->analysis->getSourceFile();
        if (!str_contains($source->contents, '__CG__')) {
            return;
        }

        if (!preg_match_all('/\\\\?(?:[A-Za-z_]\w*\\\\)*__CG__\\\\[A-Za-z_][\w\\\\]*/', $source->contents, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[0] as [$name, $offset]) {
            $context->report(
                Level::Error,
                'proxy-class-name',
                Issue::at(
                    sprintf('Doctrine proxy class %s must not be referenced directly; use the entity class instead.', ltrim($name, '\\')),
                    new SourceLocation($source->path, new Span($offset, $offset + strlen($name))),
                ),
            );
        }
    }
}
