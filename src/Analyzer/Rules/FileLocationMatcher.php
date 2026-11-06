<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer\Rules;

use Mago\Sdk\SourceLocation;

use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function substr;

final class FileLocationMatcher
{
    public static function matches(SourceLocation $location, string $file): bool
    {
        if ($location->file === null) {
            return false;
        }

        $locationFile = self::normalize($location->file);
        $analysisFile = self::normalize($file);

        return $locationFile === $analysisFile
            || str_ends_with($locationFile, '/' . $analysisFile)
            || str_ends_with($analysisFile, '/' . $locationFile);
    }

    private static function normalize(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        while (str_starts_with($file, './')) {
            $file = substr($file, 2);
        }

        return $file;
    }
}