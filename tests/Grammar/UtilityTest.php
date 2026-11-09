<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Statement\AttachStatement;
use Inilim\SqliteParser\Ast\Statement\DetachStatement;
use Inilim\SqliteParser\Ast\Statement\ExplainStatement;
use Inilim\SqliteParser\Ast\Statement\PragmaStatement;
use Inilim\SqliteParser\Ast\Statement\SavepointStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TransactionStatement;
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\TestCase;

final class UtilityTest extends TestCase
{
    private function parse(string $sql): Script
    {
        $parser = new SqliteParser();
        $result = $parser->parse(StringSource::createFromString($sql));
        self::assertInstanceOf(Script::class, $result);

        /** @var Script $result */
        return $result;
    }

    private function first(string $sql): Statement
    {
        $script = $this->parse($sql);
        self::assertNotEmpty($script->statements);

        return $script->statements[0];
    }

    // ------------------------------------------------------------------------
    // PRAGMA
    // ------------------------------------------------------------------------

    public function testPragmaBare(): void
    {
        $stmt = $this->first('PRAGMA user_version;');
        self::assertInstanceOf(PragmaStatement::class, $stmt);
        /** @var PragmaStatement $stmt */
        self::assertSame('user_version', $stmt->name);
        self::assertNull($stmt->schema);
        self::assertNull($stmt->value);
    }

    public function testPragmaEqualsOn(): void
    {
        $stmt = $this->first('PRAGMA foreign_keys = ON;');
        self::assertInstanceOf(PragmaStatement::class, $stmt);
        /** @var PragmaStatement $stmt */
        self::assertSame('foreign_keys', $stmt->name);
        self::assertInstanceOf(Literal::class, $stmt->value);
        /** @var Literal $lit */
        $lit = $stmt->value;
        self::assertSame('ON', $lit->value);
    }

    public function testPragmaFunctionForm(): void
    {
        $stmt = $this->first('PRAGMA cache_size(1000);');
        self::assertInstanceOf(PragmaStatement::class, $stmt);
        /** @var PragmaStatement $stmt */
        self::assertSame('cache_size', $stmt->name);
        self::assertInstanceOf(Literal::class, $stmt->value);
        /** @var Literal $lit */
        $lit = $stmt->value;
        self::assertSame(1000, $lit->value);
    }

    public function testPragmaWithSchema(): void
    {
        $stmt = $this->first('PRAGMA main.cache_size = 2000;');
        self::assertInstanceOf(PragmaStatement::class, $stmt);
        /** @var PragmaStatement $stmt */
        self::assertSame('main', $stmt->schema);
        self::assertSame('cache_size', $stmt->name);
    }

    // ------------------------------------------------------------------------
    // ATTACH / DETACH
    // ------------------------------------------------------------------------

    public function testAttach(): void
    {
        $stmt = $this->first("ATTACH 'extra.db' AS extra;");
        self::assertInstanceOf(AttachStatement::class, $stmt);
        /** @var AttachStatement $stmt */
        self::assertSame('extra', $stmt->schemaName);
        self::assertInstanceOf(Literal::class, $stmt->expression);
    }

    public function testDetach(): void
    {
        $stmt = $this->first('DETACH extra;');
        self::assertInstanceOf(DetachStatement::class, $stmt);
        /** @var DetachStatement $stmt */
        self::assertSame('extra', $stmt->schemaName);
    }

    // ------------------------------------------------------------------------
    // Транзакции
    // ------------------------------------------------------------------------

    public function testBegin(): void
    {
        $stmt = $this->first('BEGIN;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('BEGIN', $stmt->type);
        self::assertSame('', $stmt->mode);
    }

    public function testBeginImmediate(): void
    {
        $stmt = $this->first('BEGIN IMMEDIATE TRANSACTION;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('BEGIN', $stmt->type);
        self::assertSame('IMMEDIATE', $stmt->mode);
    }

    public function testCommit(): void
    {
        $stmt = $this->first('COMMIT;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('COMMIT', $stmt->type);
    }

    public function testEndTransaction(): void
    {
        $stmt = $this->first('END TRANSACTION;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('END', $stmt->type);
    }

    public function testRollback(): void
    {
        $stmt = $this->first('ROLLBACK;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('ROLLBACK', $stmt->type);
        self::assertFalse($stmt->toSavepoint);
    }

    public function testRollbackToSavepoint(): void
    {
        $stmt = $this->first('ROLLBACK TO SAVEPOINT sp1;');
        self::assertInstanceOf(TransactionStatement::class, $stmt);
        /** @var TransactionStatement $stmt */
        self::assertSame('ROLLBACK', $stmt->type);
        self::assertTrue($stmt->toSavepoint);
        self::assertSame('sp1', $stmt->name);
    }

    // ------------------------------------------------------------------------
    // SAVEPOINT / RELEASE
    // ------------------------------------------------------------------------

    public function testSavepoint(): void
    {
        $stmt = $this->first('SAVEPOINT sp1;');
        self::assertInstanceOf(SavepointStatement::class, $stmt);
        /** @var SavepointStatement $stmt */
        self::assertSame('SAVEPOINT', $stmt->type);
        self::assertSame('sp1', $stmt->name);
    }

    public function testRelease(): void
    {
        $stmt = $this->first('RELEASE SAVEPOINT sp1;');
        self::assertInstanceOf(SavepointStatement::class, $stmt);
        /** @var SavepointStatement $stmt */
        self::assertSame('RELEASE', $stmt->type);
        self::assertSame('sp1', $stmt->name);
    }

    public function testReleaseShort(): void
    {
        $stmt = $this->first('RELEASE sp1;');
        self::assertInstanceOf(SavepointStatement::class, $stmt);
        /** @var SavepointStatement $stmt */
        self::assertSame('RELEASE', $stmt->type);
        self::assertSame('sp1', $stmt->name);
    }

    // ------------------------------------------------------------------------
    // EXPLAIN
    // ------------------------------------------------------------------------

    public function testExplain(): void
    {
        $stmt = $this->first('EXPLAIN SELECT 1;');
        self::assertInstanceOf(ExplainStatement::class, $stmt);
        /** @var ExplainStatement $stmt */
        self::assertFalse($stmt->queryPlan);
        self::assertInstanceOf(SelectStatement::class, $stmt->statement);
    }

    public function testExplainQueryPlan(): void
    {
        $stmt = $this->first('EXPLAIN QUERY PLAN SELECT 1;');
        self::assertInstanceOf(ExplainStatement::class, $stmt);
        /** @var ExplainStatement $stmt */
        self::assertTrue($stmt->queryPlan);
        self::assertInstanceOf(SelectStatement::class, $stmt->statement);
    }

    // ------------------------------------------------------------------------
    // Скрипт с несколькими инструкциями
    // ------------------------------------------------------------------------

    public function testTransactionScript(): void
    {
        $script = $this->parse(
            'BEGIN;'
            . ' INSERT INTO t (a) VALUES (1);'
            . ' COMMIT;'
        );

        self::assertCount(3, $script->statements);
        self::assertInstanceOf(TransactionStatement::class, $script->statements[0]);
        self::assertInstanceOf(\Inilim\SqliteParser\Ast\Statement\InsertStatement::class, $script->statements[1]);
        self::assertInstanceOf(TransactionStatement::class, $script->statements[2]);

        /** @var TransactionStatement $begin */
        $begin = $script->statements[0];
        self::assertSame('BEGIN', $begin->type);

        /** @var TransactionStatement $commit */
        $commit = $script->statements[2];
        self::assertSame('COMMIT', $commit->type);
    }
}