<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Gimmickmedia\Mago\Doctrine\DoctrineConfig;
use Throwable;

use function array_column;
use function array_key_last;
use function array_pop;
use function array_replace;
use function class_exists;
use function file_get_contents;
use function is_array;
use function is_callable;
use function is_file;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function stripcslashes;
use function strlen;
use function substr;
use function trim;

/**
 * Finds DBAL type classes from Doctrine's runtime registry and Symfony's standard config files.
 */
final class DbalTypeMapLoader
{
    /** @var array<string, class-string>|null */
    private ?array $types = null;

    public function __construct(
        private readonly DoctrineConfig $config,
        private readonly ObjectManagerLoader $objectManagerLoader,
    ) {}

    /** @return array<string, class-string> */
    public function load(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $types = [];
        foreach ($this->config->symfonyConfigPaths ?? ['config/packages/doctrine.yaml'] as $path) {
            $absolutePath = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
            if (is_file($absolutePath)) {
                $types = array_replace($types, $this->readTypes($absolutePath));
            }
        }

        if ($this->config->objectManagerLoader !== null) {
            $this->objectManagerLoader->load();
            $types = array_replace($types, $this->registeredTypes());
        }

        return $this->types = array_replace($types, $this->config->types);
    }

    /** @return array<string, class-string> */
    private function readTypes(string $path): array
    {
        if (class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            try {
                /** @var mixed $config */
                $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
                $types = $config['doctrine']['dbal']['types'] ?? null;
                if (is_array($types)) {
                    return $this->normalizeTypes($types);
                }
            } catch (Throwable) {
                // Fall back to the narrow parser for Doctrine's type registration block.
            }
        }

        $contents = file_get_contents($path);

        return $contents === false ? [] : $this->parseTypes($contents);
    }

    /**
     * @param array<mixed> $types
     * @return array<string, class-string>
     */
    private function normalizeTypes(array $types): array
    {
        $normalized = [];
        foreach ($types as $name => $definition) {
            if (is_string($definition)) {
                $normalized[(string) $name] = $definition;
            } elseif (is_array($definition) && is_string($definition['class'] ?? null)) {
                $normalized[(string) $name] = $definition['class'];
            }
        }

        return $normalized;
    }

    /** @return array<string, class-string> */
    private function parseTypes(string $contents): array
    {
        $types = [];
        $stack = [];
        $pendingType = null;

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (!preg_match('/^( *)([A-Za-z0-9_.@-]+):(?:\s*(.*))?$/', $line, $match)) {
                continue;
            }

            $indent = strlen($match[1]);
            $key = $match[2];
            $value = trim($match[3] ?? '');
            while ($stack !== [] && $stack[array_key_last($stack)]['indent'] >= $indent) {
                array_pop($stack);
            }
            $parentPath = array_column($stack, 'key');

            if ($parentPath === ['doctrine', 'dbal', 'types']) {
                $pendingType = $key;
                if ($value !== '') {
                    $class = self::classValue($value);
                    if ($class !== null) {
                        $types[$key] = $class;
                    }
                    $pendingType = null;
                } else {
                    $stack[] = ['key' => $key, 'indent' => $indent];
                }

                continue;
            }

            if ($parentPath === ['doctrine', 'dbal', 'types', $pendingType] && $key === 'class') {
                $class = self::classValue($value);
                if ($pendingType !== null && $class !== null) {
                    $types[$pendingType] = $class;
                }
            }

            if ($value === '') {
                $stack[] = ['key' => $key, 'indent' => $indent];
            }
        }

        return $types;
    }

    private static function classValue(string $value): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '{')) {
            if (!preg_match('/(?:^|,)\s*class\s*:\s*("[^"]*"|\'[^\']*\'|[^,}]+)\s*/', $value, $match)) {
                return null;
            }
            $value = $match[1];
        }

        $value = self::withoutComment($value);
        $lastIndex = strlen($value) - 1;
        if ($lastIndex > 0 && (($value[0] === "'" && $value[$lastIndex] === "'") || ($value[0] === '"' && $value[$lastIndex] === '"'))) {
            $quote = $value[0];
            $value = substr($value, 1, -1);
            if ($quote === "'") {
                $value = str_replace("''", "'", $value);
            } else {
                $value = stripcslashes($value);
            }
        }

        return $value !== '' && !str_contains($value, '%env(') ? $value : null;
    }

    private static function withoutComment(string $value): string
    {
        return trim(preg_replace('/\s+#.*$/', '', $value) ?? $value);
    }

    /** @return array<string, class-string> */
    private function registeredTypes(): array
    {
        $typeClass = 'Doctrine\\DBAL\\Types\\Type';
        if (!class_exists($typeClass) || !is_callable([$typeClass, 'getTypesMap'])) {
            return [];
        }

        try {
            /** @var mixed $types */
            $types = $typeClass::getTypesMap();
        } catch (Throwable) {
            return [];
        }

        if (!is_array($types)) {
            return [];
        }

        $customTypes = [];
        foreach ($types as $name => $class) {
            if (!is_string($class) || str_starts_with(ltrim($class, '\\'), 'Doctrine\\DBAL\\Types\\')) {
                continue;
            }
            $customTypes[(string) $name] = $class;
        }

        return $customTypes;
    }
}