<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\UnaccentRules;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;
use function array_map;
use function implode;
use function is_resource;
use function sprintf;
use function stream_get_contents;

final class UnaccentExpressionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        try {
            $this->connection->executeStatement('CREATE EXTENSION IF NOT EXISTS unaccent');
        } catch (Exception $e) {
            self::markTestSkipped('The unaccent extension is not available: ' . $e->getMessage());
        }
    }

    #[DataProvider('stringProvider')]
    public function testUnaccent(string $string, string $expected): void
    {
        self::assertSame($expected, $this->unaccent($string));
    }

    /** @return iterable<string, array{string, string}> */
    public static function stringProvider(): iterable
    {
        yield 'French' => ['Crème brûlée, garçon, Noël, cœur', 'Creme brulee, garcon, Noel, coeur'];
        yield 'German' => ['Größe, Ärger, Übel', 'Grosse, Arger, Ubel'];
        yield 'Nordic' => ['Ærøskøbing, Åland, Þórshöfn', 'AEroskobing, Aland, THorshofn'];
        yield 'Spanish' => ['Señor Núñez', 'Senor Nunez'];
        yield 'Polish' => ['Łódź, Gdańsk, Kraków', 'Lodz, Gdansk, Krakow'];
        yield 'Czech' => ['Příliš žluťoučký kůň', 'Prilis zlutoucky kun'];
        yield 'Hungarian' => ['Árvíztűrő tükörfúrógép', 'Arvizturo tukorfurogep'];
        yield 'Turkish' => ['İstanbul, ılık, şiş', 'Istanbul, ilik, sis'];
        yield 'Romanian' => ['Brașov, Timișoara', 'Brasov, Timisoara'];
        yield 'Dutch' => ['Ĳsselmeer', 'IJsselmeer'];
        yield 'case' => ['ÉCOLE École école', 'ECOLE Ecole ecole'];
        yield 'unaccented' => ['Hello, World! 0123 \\"_%', 'Hello, World! 0123 \\"_%'];
        yield 'other scripts' => ['中文 😀', '中文 😀'];
    }

    public function testLetters(): void
    {
        $replacements = UnaccentRules::getReplacements();

        self::assertSame(
            implode('|', $replacements),
            $this->unaccent(implode('|', array_keys($replacements))),
        );
    }

    public function testOtherCharacters(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('The rules of the unaccent extension apply on PostgreSQL.');
        }

        self::assertSame('× ÷ © ¼ Ạ ạ ǅ ﬁ', $this->unaccent('× ÷ © ¼ Ạ ạ ǅ ﬁ'));
    }

    public function testNull(): void
    {
        self::assertNull($this->unaccent(null));
    }

    public function testColumns(): void
    {
        $this->dropAndCreateTable(
            Table::editor()
                ->setUnquotedName('unaccent_test')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setLength(50)
                        ->setNotNull(false)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('description')
                        ->setTypeName(Types::TEXT)
                        ->setNotNull(false)
                        ->create(),
                )
                // MySQL 5.7 and MariaDB 10.5 create latin1 tables by default
                ->setOptions(['charset' => 'utf8mb4'])
                ->create(),
        );

        $this->connection->insert('unaccent_test', ['id' => 1, 'name' => 'Malmö', 'description' => 'Tårnby']);
        $this->connection->insert('unaccent_test', ['id' => 2, 'name' => 'Mâcon', 'description' => null]);
        $this->connection->insert('unaccent_test', ['id' => 3, 'name' => null, 'description' => 'Šibenik']);

        $platform = $this->connection->getDatabasePlatform();
        $actual   = [];

        // each row is read before fetching the next one, which reuses the LOB of the previous one with PDO_OCI
        foreach (
            $this->connection->iterateNumeric(sprintf(
                'SELECT id, %s, %s FROM unaccent_test ORDER BY id',
                $platform->getUnaccentExpression('name'),
                $platform->getUnaccentExpression('description'),
            )) as [$id, $name, $description]
        ) {
            $actual[(int) $id] = [$name, $this->stringValue($description)];
        }

        self::assertSame([
            1 => ['Malmo', 'Tarnby'],
            2 => ['Macon', null],
            3 => [null, 'Sibenik'],
        ], $actual);
    }

    public function testComparison(): void
    {
        $this->dropAndCreateTable(
            Table::editor()
                ->setUnquotedName('unaccent_test')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setLength(50)
                        ->create(),
                )
                ->setOptions(['charset' => 'utf8mb4'])
                ->create(),
        );

        foreach ([1 => 'Malmö', 2 => 'Malmo', 3 => 'MALMÖ', 4 => 'Malme', 5 => 'Malmø'] as $id => $name) {
            $this->connection->insert('unaccent_test', ['id' => $id, 'name' => $name]);
        }

        $platform = $this->connection->getDatabasePlatform();

        self::assertSame([1, 2, 3, 5], array_map('intval', $this->connection->fetchFirstColumn(sprintf(
            'SELECT id FROM unaccent_test WHERE LOWER(%s) = LOWER(%s) ORDER BY id',
            $platform->getUnaccentExpression('name'),
            $platform->getUnaccentExpression('?'),
        ), ['MALMO'])));
    }

    private function unaccent(?string $string): ?string
    {
        $platform = $this->connection->getDatabasePlatform();

        // Db2 considers the untyped parameter markers of REPLACE() as VARCHAR(254)
        $parameter = $platform instanceof DB2Platform ? 'CAST(? AS VARCHAR(1000))' : '?';

        return $this->stringValue($this->connection->fetchOne(
            $platform->getDummySelectSQL($platform->getUnaccentExpression($parameter)),
            [$string],
        ));
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if ($value === null) {
            return null;
        }

        self::assertIsString($value);

        return $value;
    }
}
