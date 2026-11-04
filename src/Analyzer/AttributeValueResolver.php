<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\AttributeArgumentMetadata;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\EnumType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\SourceLocation;

use function count;
use function explode;
use function file_get_contents;
use function is_file;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_split;
use function strcasecmp;
use function str_contains;
use function stripcslashes;
use function str_starts_with;
use function strtolower;
use function strrpos;
use function strlen;
use function substr;
use function trim;

/**
 * Resolves constant attribute argument values, including class constant references such as `Types::BOOLEAN`.
 *
 * Mago only exposes the class of a class constant fetch as the argument value type, so the constant
 * name is read from the argument source and resolved through the codebase.
 */
final class AttributeValueResolver
{
    /** Pseudo position that never matches a positional argument. */
    public const NAMED_ONLY = -1;

    private const DBAL_TYPES_CLASSES = ['Doctrine\\DBAL\\Types\\Types', 'Doctrine\\DBAL\\Types\\Type'];

    /** @var array<string, string|false> */
    private array $sources = [];

    public function __construct(private readonly ?Codebase $codebase = null)
    {
    }

    public function string(?AttributeMetadata $attribute, int $position, string ...$names): ?string
    {
        $argument = $attribute?->getArgument($position, ...$names);
        if ($argument === null) {
            return null;
        }

        $type = $argument->valueType;

        return $type?->getLiteralClassString()
            ?? $type?->getLiteralString()
            ?? $this->classConstant($argument);
    }

    public function classConstantClass(?AttributeMetadata $attribute, int $position, string ...$names): ?string
    {
        $argument = $attribute?->getArgument($position, ...$names);

        return $argument === null ? null : $this->classConstantReference($argument)['class'];
    }

    public function classConstant(AttributeArgumentMetadata $argument): ?string
    {
        return $this->classConstantReference($argument)['value'];
    }

    /** @return array{value: ?string, class: ?string} */
    private function classConstantReference(AttributeArgumentMetadata $argument): array
    {
        $source = $this->sourceText($argument->valueLocation);
        if (
            $source === null
            || preg_match('/^(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*::\s*([A-Za-z_][A-Za-z0-9_]*)$/', trim($source), $matches) !== 1
            || strcasecmp($matches[2], 'class') === 0
        ) {
            return ['value' => null, 'class' => null];
        }

        $constant = $matches[2];
        $class = self::className($argument->valueType)
            ?? $this->resolveClassName($matches[1], $argument->valueLocation?->file);

        if ($class !== null && $this->codebase !== null) {
            $metadata = $this->codebase->getClassConstant($class, $constant);
            $value = $metadata?->inferredType?->getLiteralString()
                ?? $metadata?->type?->type->getLiteralString()
                ?? $metadata?->declaredType?->type->getLiteralString();
            $value ??= $metadata === null ? null : $this->constantSourceValue($metadata->location, $constant);
            if ($value === null && self::isDbalTypesClass($class)) {
                $value = self::dbalTypeConstant($constant);
            }

            return ['value' => $value, 'class' => $class];
        }

        if ($class !== null && self::isDbalTypesClass($class)) {
            return ['value' => self::dbalTypeConstant($constant), 'class' => $class];
        }

        return ['value' => null, 'class' => $class];
    }

    /**
     * Value of a `Doctrine\DBAL\Types\Types` constant, used when DBAL is not part of the analyzed sources.
     */
    public static function dbalTypeConstant(string $constant): string
    {
        return match ($constant) {
            'DATE_MUTABLE' => 'date',
            'DATETIME_MUTABLE' => 'datetime',
            'DATETIMETZ_MUTABLE' => 'datetimetz',
            'TIME_MUTABLE' => 'time',
            default => strtolower($constant),
        };
    }

    private static function className(?Type $type): ?string
    {
        if ($type === null || count($type->atomicTypes) !== 1) {
            return null;
        }

        $atomicType = $type->atomicTypes[0];

        return match (true) {
            $atomicType instanceof NamedObjectType, $atomicType instanceof EnumType => $atomicType->name,
            default => null,
        };
    }

    private function sourceText(?SourceLocation $location): ?string
    {
        if ($location === null || $location->file === null) {
            return null;
        }

        $file = $location->file;
        if (!isset($this->sources[$file])) {
            $this->sources[$file] = is_file($file) ? file_get_contents($file) : false;
        }

        $contents = $this->sources[$file];
        if ($contents === false) {
            return null;
        }

        return substr($contents, $location->span->start, $location->span->length());
    }

    private function constantSourceValue(SourceLocation $location, string $constant): ?string
    {
        $contents = $this->sourceFile($location->file);
        if ($contents === null) {
            return null;
        }

        $start = max(0, $location->span->start - 256);
        $window = substr($contents, $start, min(1024, strlen($contents) - $start));
        $quotedConstant = preg_quote($constant, '/');
        if (preg_match('/\bconst\s+' . $quotedConstant . '\s*=\s*([\'\"])(.*?)\1/s', $window, $matches) !== 1) {
            return null;
        }

        return stripcslashes($matches[2]);
    }

    private function resolveClassName(string $name, ?string $file): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $contents = $this->sourceFile($file);
        if ($contents === null) {
            return $name;
        }

        if (str_starts_with(strtolower($name), 'namespace\\')) {
            preg_match('/^namespace\s+([^;{]+)[;{]/m', $contents, $namespace);

            return isset($namespace[1]) ? trim($namespace[1]) . '\\' . substr($name, 10) : substr($name, 10);
        }

        $parts = explode('\\', $name, 2);
        $alias = $parts[0];
        if (preg_match_all('/^\s*use\s+(?!function\b|const\b)([^;]+);/mi', $contents, $imports)) {
            foreach ($imports[1] as $import) {
                if (str_contains($import, '{')) {
                    continue;
                }

                $segments = preg_split('/\s+as\s+/i', trim($import));
                $importedName = ltrim($segments[0], '\\');
                $importAlias = $segments[1] ?? substr($importedName, (strrpos($importedName, '\\') ?: -1) + 1);
                if (strcasecmp($alias, trim($importAlias)) === 0) {
                    return $importedName . (isset($parts[1]) ? '\\' . $parts[1] : '');
                }
            }
        }

        preg_match('/^namespace\s+([^;{]+)[;{]/m', $contents, $namespace);

        return isset($namespace[1]) ? trim($namespace[1]) . '\\' . $name : $name;
    }

    private function sourceFile(?string $file): ?string
    {
        if ($file === null) {
            return null;
        }

        if (!isset($this->sources[$file])) {
            $this->sources[$file] = is_file($file) ? file_get_contents($file) : false;
        }

        $source = $this->sources[$file];

        return $source === false ? null : $source;
    }

    private static function isDbalTypesClass(string $class): bool
    {
        foreach (self::DBAL_TYPES_CLASSES as $typesClass) {
            if (strcasecmp($class, $typesClass) === 0) {
                return true;
            }
        }

        return false;
    }
}
