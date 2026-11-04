<?php

declare(strict_types=1);

namespace Gimmickmedia\Mago\Doctrine\Tests\Unit;

use Gimmickmedia\Mago\Doctrine\Analyzer\Dql\DqlValidator;

final class DqlValidatorTest extends DqlTestCase
{
    public function testValidQueriesHaveNoErrors(): void
    {
        self::assertSame([], $this->validate('SELECT u, g FROM Acme\\User u JOIN u.group g WHERE g.title = :title'));
    }

    public function testReportsUnknownEntities(): void
    {
        self::assertSame(
            ['Unknown Doctrine entity class Acme\\Missing in DQL FROM clause.'],
            $this->validate('SELECT m FROM Acme\\Missing m'),
        );
    }

    public function testReportsUnknownFieldsAndAssociations(): void
    {
        self::assertSame(['Unknown Doctrine field u.missing in DQL.'], $this->validate('SELECT u.missing FROM Acme\\User u'));
        self::assertSame(
            ['Unknown Doctrine association u.posts in DQL JOIN clause.'],
            $this->validate('SELECT u FROM Acme\\User u JOIN u.posts p'),
        );
    }

    public function testReportsParseErrors(): void
    {
        self::assertSame(
            ['DQL must contain a SELECT clause and a mapped entity in FROM.'],
            $this->validate('SELEC u FROM'),
        );
    }

    /** @return list<string> */
    private function validate(string $dql): array
    {
        return (new DqlValidator($this->metadata(), $this->config()))->validate($dql);
    }
}
