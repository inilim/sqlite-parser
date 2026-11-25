<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Порт `reject_invalid.rs` (синтаксическая часть) + негативных кейсов `smoke.rs`.
 *
 * В Rust проверяется `ParseOutcome::Err`. У нас phplrt бросает исключение
 * лексера/парсера, поэтому проверка сформулирована как «разбор падает».
 *
 * Семантические кейсы источника (RETURNING внутри триггера, TEMP TRIGGER с
 * квалифицированным именем, storage-keyword generated columns, digit
 * separators) сознательно не перенесены — наш парсер только синтаксический.
 * Подробности — в tests/Ported/README.md.
 */
final class RejectInvalidSqlTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * @dataProvider provideRejectedSql
     */
    public function testInvalidSqlIsRejected(string $sql): void
    {
        $error = null;

        try {
            $script = $this->parser->parse($sql);
        } catch (Throwable $e) {
            $error = $e;
        }

        self::assertInstanceOf(
            Throwable::class,
            $error,
            'ожидали ошибку разбора для: ' . $sql
            . (\is_null($error) ? ' (получено инструкций: ' . \count($script->statements) . ')' : ''),
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideRejectedSql(): iterable
    {
        // bare SELECT / оборванный список колонок
        yield 'bare select' => ['SELECT'];
        yield 'bare select with space' => ['SELECT '];
        yield 'select from without columns' => ['SELECT FROM t'];
        yield 'select dangling comma expr' => ['SELECT , a FROM t'];

        // оборванный FROM
        yield 'select star from nothing' => ['SELECT * FROM'];
        yield 'select from nothing with space' => ['SELECT a FROM '];

        // мусор вместо инструкции
        yield 'not valid sql' => ['NOT VALID SQL;'];
        yield 'random words' => ['THIS IS NOT SQL'];

        // оборванные выражения
        yield 'dangling plus' => ['SELECT 1 +'];
        yield 'unclosed paren' => ['SELECT (1'];
        yield 'unexpected close paren' => ['SELECT 1)'];
        yield 'dangling where' => ['SELECT * FROM t WHERE'];
        yield 'dangling and' => ['SELECT * FROM t WHERE a = 1 AND'];
        yield 'dangling group' => ['SELECT a FROM t GROUP'];
        yield 'dangling order' => ['SELECT a FROM t ORDER'];
        yield 'dangling order by' => ['SELECT a FROM t ORDER BY'];
        yield 'dangling limit' => ['SELECT * FROM t LIMIT'];

        // оборванный CASE / CAST
        yield 'case without end' => ['SELECT CASE WHEN 1 THEN 1'];
        yield 'case without then' => ['SELECT CASE WHEN 1 END'];
        yield 'cast without type' => ['SELECT CAST(x AS)'];
        yield 'cast without as' => ['SELECT CAST(x INTEGER)'];

        // оборванный DML
        yield 'insert into nothing' => ['INSERT INTO'];
        yield 'insert without values' => ['INSERT INTO t VALUES'];
        yield 'insert unclosed row' => ['INSERT INTO t (a) VALUES (1'];
        yield 'update without table' => ['UPDATE'];
        yield 'update without set' => ['UPDATE t'];
        yield 'update empty set' => ['UPDATE t SET'];
        yield 'update dangling assignment' => ['UPDATE t SET a ='];
        yield 'delete bare' => ['DELETE'];
        yield 'delete from nothing' => ['DELETE FROM'];

        // оборванный DDL
        yield 'create bare' => ['CREATE'];
        yield 'create table bare' => ['CREATE TABLE'];
        yield 'create table without body' => ['CREATE TABLE t'];
        yield 'create table unclosed body' => ['CREATE TABLE t (a INT'];
        yield 'create index without on' => ['CREATE INDEX i'];
        yield 'create index without columns' => ['CREATE INDEX i ON t'];
        yield 'create view without select' => ['CREATE VIEW v AS'];
        yield 'alter without action' => ['ALTER TABLE t'];
        yield 'drop bare' => ['DROP'];
        yield 'drop table without name' => ['DROP TABLE'];

        // оборванные утилиты
        yield 'pragma bare keyword' => ['PRAGMA'];
        yield 'attach without as' => ["ATTACH 'x.db' AS"];
        yield 'detach without name' => ['DETACH'];
        yield 'explain without statement' => ['EXPLAIN'];
        yield 'explain query plan without statement' => ['EXPLAIN QUERY PLAN'];
    }

    /**
     * Контрольная группа: «соседний» валидный SQL обязан приниматься.
     *
     * @dataProvider provideAcceptedSql
     */
    public function testValidNeighboursAreAccepted(string $sql): void
    {
        $script = $this->parser->parse($sql);

        self::assertCount(1, $script->statements, 'скрипт: ' . $sql);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideAcceptedSql(): iterable
    {
        yield 'select literal' => ['SELECT 1'];
        yield 'select columns' => ['SELECT a, b FROM t'];
        yield 'select where' => ['SELECT * FROM t WHERE a = 1'];
        yield 'select group order limit' => ['SELECT a FROM t GROUP BY a ORDER BY a LIMIT 10'];
        yield 'select paren expr' => ['SELECT (1 + 2) * 3'];
        yield 'select case' => ['SELECT CASE WHEN x > 0 THEN 1 ELSE 0 END'];
        yield 'select cast' => ['SELECT CAST(x AS INTEGER)'];
        yield 'insert values' => ['INSERT INTO t (a) VALUES (1)'];
        yield 'update set' => ['UPDATE t SET a = 1'];
        yield 'delete where' => ['DELETE FROM t WHERE a = 1'];
        yield 'create table' => ['CREATE TABLE t (a INT)'];
        yield 'create index' => ['CREATE INDEX i ON t (a)'];
        yield 'create view' => ['CREATE VIEW v AS SELECT 1'];
        yield 'alter rename' => ['ALTER TABLE t RENAME TO u'];
        yield 'drop table' => ['DROP TABLE t'];
        yield 'pragma' => ['PRAGMA user_version'];
        yield 'attach' => ["ATTACH 'x.db' AS x"];
        yield 'detach' => ['DETACH x'];
        yield 'explain' => ['EXPLAIN SELECT 1'];
    }

    /**
     * Порт `smoke.rs::parse_error_has_message_and_offset` (частично):
     * ошибка разбора должна нести непустое сообщение.
     */
    public function testParseErrorHasNonEmptyMessage(): void
    {
        $message = null;

        try {
            $this->parser->parse('NOT VALID SQL;');
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }

        self::assertIsString($message, 'ожидали исключение с сообщением');
        self::assertNotSame('', \trim($message), 'сообщение об ошибке не должно быть пустым');
    }
}