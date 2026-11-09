<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\CreateIndexStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\DropStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\PragmaStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TransactionStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Parser\SqliteParser;
use PHPUnit\Framework\TestCase;
use Phplrt\Source\StringSource;

/**
 * Сквозные сценарии: многоинструкционный SQL → Script.
 * Проверяют, что парсер корректно «склеивает» инструкции через `;`.
 */
final class FullScriptTest extends TestCase
{
    private function parse(string $sql): Script
    {
        $ast = (new SqliteParser())->parse(StringSource::createFromString($sql));

        self::assertInstanceOf(Script::class, $ast);

        return $ast;
    }

    public function testCreateInsertSelectScript(): void
    {
        $ast = $this->parse(
            'CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL);'
            . 'INSERT INTO t (id, name) VALUES (1, \'a\'), (2, \'b\');'
            . 'SELECT id, name FROM t WHERE id = 1;'
        );

        self::assertCount(3, $ast->statements);
        self::assertInstanceOf(CreateTableStatement::class, $ast->statements[0]);
        self::assertInstanceOf(InsertStatement::class, $ast->statements[1]);
        self::assertInstanceOf(SelectStatement::class, $ast->statements[2]);
    }

    public function testDdlPipeline(): void
    {
        $ast = $this->parse(
            'CREATE TABLE t (id INTEGER);'
            . 'CREATE INDEX idx_t_id ON t (id);'
            . 'DROP TABLE t;'
        );

        self::assertCount(3, $ast->statements);
        self::assertInstanceOf(CreateTableStatement::class, $ast->statements[0]);
        self::assertInstanceOf(CreateIndexStatement::class, $ast->statements[1]);
        self::assertInstanceOf(DropStatement::class, $ast->statements[2]);
    }

    public function testTransactionScript(): void
    {
        $ast = $this->parse(
            'BEGIN IMMEDIATE TRANSACTION;'
            . 'INSERT INTO t (a) VALUES (1);'
            . 'UPDATE t SET a = 2 WHERE a = 1;'
            . 'COMMIT;'
        );

        self::assertCount(4, $ast->statements);
        self::assertInstanceOf(TransactionStatement::class, $ast->statements[0]);
        self::assertInstanceOf(InsertStatement::class, $ast->statements[1]);
        self::assertInstanceOf(UpdateStatement::class, $ast->statements[2]);
        self::assertInstanceOf(TransactionStatement::class, $ast->statements[3]);
    }

    public function testUtilityAndQueryMix(): void
    {
        $ast = $this->parse(
            'PRAGMA foreign_keys = ON;'
            . 'SELECT * FROM t;'
        );

        self::assertCount(2, $ast->statements);
        self::assertInstanceOf(PragmaStatement::class, $ast->statements[0]);
        self::assertInstanceOf(SelectStatement::class, $ast->statements[1]);
    }

    public function testTrailingSemicolonIsOptional(): void
    {
        $ast = $this->parse('SELECT 1');

        self::assertCount(1, $ast->statements);
        self::assertInstanceOf(SelectStatement::class, $ast->statements[0]);
    }
}