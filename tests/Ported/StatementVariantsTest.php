<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Statement\AlterTableStatement;
use Inilim\SqliteParser\Ast\Statement\AttachStatement;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\CreateIndexStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTriggerStatement;
use Inilim\SqliteParser\Ast\Statement\CreateViewStatement;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\DetachStatement;
use Inilim\SqliteParser\Ast\Statement\DropStatement;
use Inilim\SqliteParser\Ast\Statement\EmptyStatement;
use Inilim\SqliteParser\Ast\Statement\ExplainStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\PragmaStatement;
use Inilim\SqliteParser\Ast\Statement\SavepointStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TransactionStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Порт `open_extension.rs`.
 *
 * В Rust проверяется, что «чистый SQLite» никогда не порождает Unknown-узел
 * и что корень инструкции — известный вариант enum `Stmt`.
 * У нас аналог — типизированный класс AST-инструкции.
 */
final class StatementVariantsTest extends TestCase
{
    /**
     * @dataProvider provideStatementKinds
     *
     * @param class-string<Statement> $expectedClass
     */
    public function testStatementResolvesToExpectedClass(string $sql, string $expectedClass): void
    {
        $script = (new Parser())->parse($sql);

        self::assertInstanceOf(Script::class, $script);
        self::assertCount(1, $script->statements, 'скрипт: ' . $sql);
        self::assertInstanceOf($expectedClass, $script->statements[0], 'скрипт: ' . $sql);
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string<Statement>}>
     */
    public static function provideStatementKinds(): iterable
    {
        yield 'select' => ['SELECT 1', SelectStatement::class];
        yield 'select from' => ['SELECT a FROM t', SelectStatement::class];
        yield 'compound select' => ['SELECT 1 UNION SELECT 2', CompoundSelect::class];
        yield 'insert' => ['INSERT INTO t VALUES (1)', InsertStatement::class];
        yield 'insert select' => ['INSERT INTO t (a) SELECT 1', InsertStatement::class];
        yield 'update' => ['UPDATE t SET x = 1', UpdateStatement::class];
        yield 'delete' => ['DELETE FROM t WHERE x = 1', DeleteStatement::class];
        yield 'create table' => ['CREATE TABLE t (x INT)', CreateTableStatement::class];
        yield 'create table as select' => ['CREATE TABLE t AS SELECT 1', CreateTableStatement::class];
        yield 'create index' => ['CREATE INDEX i ON t (x)', CreateIndexStatement::class];
        yield 'create view' => ['CREATE VIEW v AS SELECT 1', CreateViewStatement::class];
        yield 'create trigger' => [
            'CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO u VALUES (1); END;',
            CreateTriggerStatement::class,
        ];
        yield 'alter table' => ['ALTER TABLE t RENAME TO u', AlterTableStatement::class];
        yield 'drop table' => ['DROP TABLE t', DropStatement::class];
        yield 'drop index' => ['DROP INDEX i', DropStatement::class];
        yield 'drop view' => ['DROP VIEW v', DropStatement::class];
        yield 'drop trigger' => ['DROP TRIGGER tr', DropStatement::class];
        yield 'pragma' => ['PRAGMA user_version', PragmaStatement::class];
        yield 'attach' => ["ATTACH 'extra.db' AS extra", AttachStatement::class];
        yield 'detach' => ['DETACH extra', DetachStatement::class];
        yield 'begin' => ['BEGIN', TransactionStatement::class];
        yield 'commit' => ['COMMIT', TransactionStatement::class];
        yield 'savepoint' => ['SAVEPOINT sp1', SavepointStatement::class];
        yield 'release' => ['RELEASE sp1', SavepointStatement::class];
        yield 'explain' => ['EXPLAIN SELECT 1', ExplainStatement::class];
        yield 'explain query plan' => ['EXPLAIN QUERY PLAN SELECT 1', ExplainStatement::class];
        yield 'bare semicolon' => [';', EmptyStatement::class];
    }

    /**
     * Аналог `pure_sqlite_never_produces_unknown_node`: любой узел верхнего
     * уровня — это конкретный подкласс Statement, а не «заглушка».
     */
    public function testEveryStatementIsConcreteStatementSubclass(): void
    {
        $sqls = [
            'SELECT 1 + 2',
            'INSERT INTO t VALUES (1)',
            'UPDATE t SET x = 1',
            'DELETE FROM t WHERE x = 1',
            'CREATE TABLE t (x INT)',
        ];

        $parser = new Parser();

        foreach ($sqls as $sql) {
            $script = $parser->parse($sql);
            self::assertCount(1, $script->statements);

            $stmt = $script->statements[0];

            self::assertInstanceOf(Statement::class, $stmt);
            self::assertNotSame(Statement::class, $stmt::class, 'узел не должен быть базовым Statement: ' . $sql);
            self::assertNotSame(EmptyStatement::class, $stmt::class, 'узел не должен быть пустым: ' . $sql);
        }
    }

    /**
     * Все инструкции сериализуются (аналог «узел разрезов резолвится»).
     */
    public function testAllStatementsAreJsonSerializable(): void
    {
        $parser = new Parser();

        $script = $parser->parse(
            'CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT);'
            . 'INSERT INTO t (id, name) VALUES (1, \'a\');'
            . 'SELECT id, name FROM t WHERE id = 1;'
            . 'UPDATE t SET name = \'b\' WHERE id = 1;'
            . 'DELETE FROM t WHERE id = 1;'
            . 'DROP TABLE t;'
        );

        self::assertCount(6, $script->statements);

        foreach ($script->statements as $stmt) {
            $json = \json_encode($stmt, JSON_THROW_ON_ERROR);
            self::assertJson($json);
        }
    }
}