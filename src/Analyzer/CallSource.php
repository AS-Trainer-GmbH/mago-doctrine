<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

final class CallSource
{
    /**
     * Returns the name of the outermost method called by the given call expression source.
     */
    public static function methodName(string $call): ?string
    {
        $call = rtrim($call);
        if (!str_ends_with($call, ')')) {
            return null;
        }

        $depth = 0;
        $quote = null;
        for ($index = strlen($call) - 1; $index >= 0; $index--) {
            $character = $call[$index];
            if ($quote !== null) {
                if ($character === $quote && ($index === 0 || $call[$index - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === ')') {
                $depth++;
            } elseif ($character === '(') {
                $depth--;
                if ($depth === 0) {
                    return preg_match('/(?:->|\?->|::)\s*(\w+)\s*$/', substr($call, 0, $index), $match) ? $match[1] : null;
                }
            }
        }

        return null;
    }
}
