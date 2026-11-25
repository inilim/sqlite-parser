<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Printer\SqlPrinter;
use PHPUnit\Framework\TestCase;

/**
 * Порт `generated.rs::format_idempotent` (адаптированный).
 *
 * В Rust форматтер обязан воспроизводить уже отформатированный SQL байт-в-байт.
 * Наш `SqlPrinter` не форматтер и не сохраняет исходное форматирование,
 * поэтому инвариант ослаблен до семантического round-trip:
 *
 *   parse(sql) → print → parse(printed) → print
 *
 *   1) напечатанный SQL снова парсится ровно в одну инструкцию того же типа;
 *   2) повторная печать совпадает с первой (идемпотентность печати).
 *
 * Регистр ключевых слов (`formatter_keyword_case.rs`) не портируется:
 * у принтера нет конфигурации.
 */
final class PrinterRoundTripTest extends TestCase
{
    /**
     * @dataProvider provideRoundTripStatements
     */
    public function testPrintedSqlReparsesAndPrintingIsIdempotent(string $sql): void
    {
        $parser = new Parser();
        $printer = new SqlPrinter();

        $original = $parser->parse($sql);
        self::assertCount(1, $original->statements, 'скрипт: ' . $sql);

        $stmt = $original->statements[0];
        $printed = $printer->print($stmt);

        self::assertNotSame('', \trim($printed), 'принтер вернул пустую строку для: ' . $sql);

        $reparsed = $parser->parse($printed);
        self::assertCount(
            1,
            $reparsed->statements,
            'напечатанный SQL не парсится в одну инструкцию: ' . $printed,
        );
        self::assertInstanceOf($stmt::class, $reparsed->statements[0], 'напечатано: ' . $printed);

        $reprinted = $printer->print($reparsed->statements[0]);
        self::assertSame($printed, $reprinted, 'печать не идемпотентна для: ' . $sql);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideRoundTripStatements(): iterable
    {
        // Базовые SELECT (аналог списка из format_idempotent)
        yield 'select literal' => ['SELECT 42'];
        yield 'select column' => ['SELECT a FROM t'];
        yield 'select columns' => ['SELECT a, b, c FROM t'];
        yield 'select star' => ['SELECT * FROM t'];
        yield 'select distinct' => ['SELECT DISTINCT a FROM t'];
        yield 'select alias' => ['SELECT a AS x FROM t'];
        yield 'select where' => ['SELECT a FROM t WHERE x = 1'];
        yield 'select table alias' => ['SELECT a FROM t AS u'];
        yield 'select qualified column' => ['SELECT t.a FROM t'];

        // Выражения
        yield 'arith' => ['SELECT 1 + 2'];
        yield 'unary minus' => ['SELECT -1'];
        yield 'logical chain' => ['SELECT a FROM t WHERE x = 1 AND y = 2'];
        yield 'is not null' => ['SELECT a FROM t WHERE email IS NOT NULL'];

        // Клаузы
        yield 'group by' => ['SELECT a, COUNT(*) FROM t GROUP BY a'];
        yield 'order by' => ['SELECT a FROM t ORDER BY a'];
        yield 'limit' => ['SELECT a FROM t LIMIT 10'];

        // DML
        yield 'insert values' => ['INSERT INTO t (a, b) VALUES (1, 2)'];
        yield 'update' => ['UPDATE t SET a = 1 WHERE x = 2'];
        yield 'delete' => ['DELETE FROM t WHERE x = 1'];

        // DDL
        yield 'create table' => ['CREATE TABLE t (a INTEGER, b TEXT)'];
        yield 'drop table' => ['DROP TABLE t'];
        yield 'drop table if exists' => ['DROP TABLE IF EXISTS t'];
    }

    /**
     * AST не должен меняться от round-trip через принтер
     * (сравнение по JSON-сериализации).
     *
     * @dataProvider provideAstStableStatements
     */
    public function testAstIsStableAcrossPrinterRoundTrip(string $sql): void
    {
        $parser = new Parser();
        $printer = new SqlPrinter();

        $first = $parser->parse($sql);
        self::assertCount(1, $first->statements);

        $printed = $printer->print($first->statements[0]);
        $second = $parser->parse($printed);
        self::assertCount(1, $second->statements, 'напечатано: ' . $printed);

        self::assertSame(
            \json_encode($first->statements[0], JSON_THROW_ON_ERROR),
            \json_encode($second->statements[0], JSON_THROW_ON_ERROR),
            'AST изменилось после round-trip через SqlPrinter; напечатано: ' . $printed,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideAstStableStatements(): iterable
    {
        yield 'select literal' => ['SELECT 42'];
        yield 'select columns' => ['SELECT id, name FROM users'];
        yield 'select star' => ['SELECT * FROM users'];
        yield 'select where' => ['SELECT id FROM users WHERE id = 1'];
        yield 'select alias' => ['SELECT id AS ident FROM users'];
        yield 'delete where' => ['DELETE FROM users WHERE id = 1'];
        yield 'update where' => ['UPDATE users SET name = \'a\' WHERE id = 1'];
    }
}