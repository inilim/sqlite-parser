<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\Assignment;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Ast\Statement\UpsertClause;
use Inilim\SqliteParser\Parser\SqliteParser;
use PHPUnit\Framework\TestCase;
use Phplrt\Source\StringSource;

final class DmlTest extends TestCase
{
    private SqliteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteParser();
    }

    private function parse(string $sql): Script
    {
        $ast = $this->parser->parse(StringSource::createFromString($sql));
        $this->assertInstanceOf(Script::class, $ast);

        return $ast;
    }

    public function testInsertValues(): void
    {
        $script = $this->parse('INSERT INTO t (a, b) VALUES (1, 2)');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertSame('t', $stmt->table);
        $this->assertSame(['a', 'b'], $stmt->columns);
        $this->assertNotNull($stmt->rows);
        $this->assertCount(1, $stmt->rows);
        $this->assertCount(2, $stmt->rows[0]);
        $this->assertNull($stmt->orAction);
        $this->assertFalse($stmt->defaultValues);
        $this->assertNull($stmt->upsert);
        $this->assertNull($stmt->returning);
    }

    public function testInsertMultipleRows(): void
    {
        $script = $this->parse('INSERT INTO t (a) VALUES (1), (2), (3)');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertNotNull($stmt->rows);
        $this->assertCount(3, $stmt->rows);
        $this->assertCount(1, $stmt->rows[0]);
    }

    public function testInsertOrReplace(): void
    {
        $script = $this->parse('INSERT OR REPLACE INTO t (a) VALUES (1)');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertSame('REPLACE', $stmt->orAction);
        $this->assertSame('t', $stmt->table);
    }

    public function testInsertDefaultValues(): void
    {
        $script = $this->parse('INSERT INTO t DEFAULT VALUES');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertTrue($stmt->defaultValues);
        $this->assertNull($stmt->rows);
        $this->assertNull($stmt->select);
    }

    public function testInsertSelect(): void
    {
        $script = $this->parse('INSERT INTO t (a) SELECT 1');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertNull($stmt->rows);
        $this->assertNotNull($stmt->select);
    }

    public function testInsertOnConflictDoNothing(): void
    {
        $script = $this->parse('INSERT INTO t VALUES (1) ON CONFLICT (a) DO NOTHING');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertInstanceOf(UpsertClause::class, $stmt->upsert);
        /** @var UpsertClause $upsert */
        $upsert = $stmt->upsert;
        $this->assertSame(['a'], $upsert->conflictTarget);
        $this->assertNull($upsert->assignments);
        $this->assertNull($upsert->where);
    }

    public function testInsertOnConflictDoUpdate(): void
    {
        $script = $this->parse('INSERT INTO t (a, b) VALUES (1, 2) ON CONFLICT (a) DO UPDATE SET b = 3');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertInstanceOf(UpsertClause::class, $stmt->upsert);
        /** @var UpsertClause $upsert */
        $upsert = $stmt->upsert;
        $this->assertSame(['a'], $upsert->conflictTarget);
        $this->assertNotNull($upsert->assignments);
        $this->assertCount(1, $upsert->assignments);
        $this->assertSame('b', $upsert->assignments[0]->column);
        $this->assertNull($upsert->where);
    }

    public function testInsertReturning(): void
    {
        $script = $this->parse('INSERT INTO t (a) VALUES (1) RETURNING id');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(InsertStatement::class, $stmt);
        /** @var InsertStatement $stmt */
        $this->assertNotNull($stmt->returning);
        $this->assertCount(1, $stmt->returning->columns);
    }

    public function testUpdate(): void
    {
        $script = $this->parse('UPDATE t SET a = 1 WHERE b = 2');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(UpdateStatement::class, $stmt);
        /** @var UpdateStatement $stmt */
        $this->assertSame('t', $stmt->table);
        $this->assertCount(1, $stmt->assignments);
        $this->assertInstanceOf(Assignment::class, $stmt->assignments[0]);
        $this->assertSame('a', $stmt->assignments[0]->column);
        $this->assertInstanceOf(BinaryOperation::class, $stmt->where);
    }

    public function testUpdateMultipleAssignments(): void
    {
        $script = $this->parse('UPDATE t SET a = 1, b = 2, c = 3');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(UpdateStatement::class, $stmt);
        /** @var UpdateStatement $stmt */
        $this->assertCount(3, $stmt->assignments);
        $this->assertSame('a', $stmt->assignments[0]->column);
        $this->assertSame('b', $stmt->assignments[1]->column);
        $this->assertSame('c', $stmt->assignments[2]->column);
    }

    public function testUpdateOrReplace(): void
    {
        $script = $this->parse('UPDATE OR REPLACE t SET a = 1');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(UpdateStatement::class, $stmt);
        /** @var UpdateStatement $stmt */
        $this->assertSame('REPLACE', $stmt->orAction);
        $this->assertSame('t', $stmt->table);
    }

    public function testUpdateReturning(): void
    {
        $script = $this->parse('UPDATE t SET a = 1 WHERE b = 2 RETURNING a');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(UpdateStatement::class, $stmt);
        /** @var UpdateStatement $stmt */
        $this->assertNotNull($stmt->returning);
        $this->assertCount(1, $stmt->returning->columns);
        $this->assertInstanceOf(BinaryOperation::class, $stmt->where);
    }

    public function testDelete(): void
    {
        $script = $this->parse('DELETE FROM t WHERE a = 1');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(DeleteStatement::class, $stmt);
        /** @var DeleteStatement $stmt */
        $this->assertSame('t', $stmt->table);
        $this->assertInstanceOf(BinaryOperation::class, $stmt->where);
        $this->assertNull($stmt->returning);
    }

    public function testDeleteNoWhere(): void
    {
        $script = $this->parse('DELETE FROM t');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(DeleteStatement::class, $stmt);
        /** @var DeleteStatement $stmt */
        $this->assertSame('t', $stmt->table);
        $this->assertNull($stmt->where);
    }

    public function testDeleteReturning(): void
    {
        $script = $this->parse('DELETE FROM t WHERE a = 1 RETURNING *');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(DeleteStatement::class, $stmt);
        /** @var DeleteStatement $stmt */
        $this->assertNotNull($stmt->returning);
        $this->assertCount(1, $stmt->returning->columns);
    }

    public function testAssignmentValueIsExpression(): void
    {
        $script = $this->parse('UPDATE t SET a = 1 + 2');
        $stmt = $script->statements[0];

        $this->assertInstanceOf(UpdateStatement::class, $stmt);
        /** @var UpdateStatement $stmt */
        $assign = $stmt->assignments[0];
        $this->assertInstanceOf(BinaryOperation::class, $assign->value);
        $this->assertSame('+', $assign->value->operator);
    }
}