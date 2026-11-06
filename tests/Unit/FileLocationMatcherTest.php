<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\Rules\FileLocationMatcher;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

final class FileLocationMatcherTest extends TestCase
{
    public function testMatchesAnAbsoluteLocationToItsProjectRelativeFile(): void
    {
        $location = new SourceLocation('/workspace/project/src/Entity.php', new Span(0, 1));

        self::assertTrue(FileLocationMatcher::matches($location, 'src/Entity.php'));
    }

    public function testDoesNotMatchDifferentFilesWithTheSameAnalysisContext(): void
    {
        $location = new SourceLocation('/workspace/project/src/Entity.php', new Span(0, 1));

        self::assertFalse(FileLocationMatcher::matches($location, 'src/Repository.php'));
    }

    public function testDoesNotMatchLocationsWithoutAFile(): void
    {
        $location = new SourceLocation(null, new Span(0, 1));

        self::assertFalse(FileLocationMatcher::matches($location, 'src/Entity.php'));
    }
}