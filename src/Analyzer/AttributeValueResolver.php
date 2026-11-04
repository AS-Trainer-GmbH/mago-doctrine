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
use function file_get_contents;
use function is_file;
use function ltrim;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strcasecmp;
use function strtolower;
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

    public function classConstant(AttributeArgumentMetadata $argument): ?string
    {
        $source = $this->sourceText($argument->valueLocation);
        if (
            $source === null
            || preg_match('/^(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*::\s*([A-Za-z_][A-Za-z0-9_]*)$/', trim($source), $matches) !== 1
            || strcasecmp($matches[2], 'class') === 0
        ) {
            return null;
        }

        $constant = $matches[2];
        $class = self::className($argument->valueType)
            ?? (str_starts_with($matches[1], '\\') ? ltrim($matches[1], '\\') : null);

        if ($class !== null && $this->codebase !== null) {
            $metadata = $this->codebase->getClassConstant($class, $constant);
            $value = $metadata?->inferredType?->getLiteralString()
                ?? $metadata?->type?->type->getLiteralString()
                ?? $metadata?->declaredType?->type->getLiteralString();
            if ($value !== null) {
                return $value;
            }
        }

        foreach (self::DBAL_TYPES_CLASSES as $typesClass) {
            if (
                ($class !== null && strcasecmp($class, $typesClass) === 0)
                || ($class === null && str_ends_with('\\' . $typesClass, '\\' . ltrim($matches[1], '\\')))
            ) {
                return self::dbalTypeConstant($constant);
            }
        }

        return null;
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
}
