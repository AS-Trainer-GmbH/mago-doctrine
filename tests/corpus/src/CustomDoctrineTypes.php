<?php

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\Types;

final class UuidType
{
    public const NAME = 'uuid';
}

final class UlidType
{
    public const NAME = 'ulid';
}

namespace Symfony\Component\Uid;

final class Uuid
{
}

final class Ulid
{
}

namespace Carbon;

final class CarbonImmutable
{
}

final class Carbon
{
}

namespace Carbon\Doctrine;

final class DateTimeImmutableType
{
    public function convertToPHPValue(mixed $value, mixed $platform): ?\Carbon\CarbonImmutable
    {
        return null;
    }
}

final class DateTimeType
{
    public function convertToPHPValue(mixed $value, mixed $platform): ?\Carbon\Carbon
    {
        return null;
    }
}

namespace Corpus\Doctrine;

final class CustomValueType
{
    public function convertToPHPValue(mixed $value, mixed $platform): ?\Corpus\Entity\CustomValue
    {
        return null;
    }
}