<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Analyzer;

use Mago\Sdk\Analyzer\Type;

final class DoctrineTypeMapper
{
    /**
     * @param array<string, Type> $customTypes
     */
    public static function toPhpType(string $dbalType, ?string $enumType = null, array $customTypes = []): ?Type
    {
        if ($enumType !== null) {
            return Type::namedObject($enumType);
        }

        return $customTypes[$dbalType] ?? match (strtolower($dbalType)) {
            'ascii_string', 'guid', 'string', 'text', 'uuid', 'decimal' => Type::string(),
            'bigint' => Type::union(Type::int(), Type::string()),
            'boolean' => Type::bool(),
            'integer', 'smallint' => Type::int(),
            'float', 'smallfloat' => Type::float(),
            'date', 'datetime', 'datetimetz', 'time' => Type::namedObject(\DateTime::class),
            'date_immutable', 'datetime_immutable', 'datetimetz_immutable', 'time_immutable' =>
                Type::namedObject(\DateTimeImmutable::class),
            'dateinterval' => Type::namedObject(\DateInterval::class),
            'simple_array' => Type::list(Type::string()),
            'json', 'json_array', 'array' => Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
            'object' => Type::object(),
            default => null,
        };
    }

    public static function nullable(Type $type, bool $nullable): Type
    {
        return $nullable ? Type::union($type, Type::null()) : $type;
    }
}