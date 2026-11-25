<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\EmptyStatement;
use Inilim\SqliteParser\Ast\Statement\ExplainStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TransactionStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Порт граничных кейсов `low_level.rs` и `smoke.rs`:
 *   - границы инструкций и `;`;
 *   - «голые» `;` как пустые инструкции;
 *   - EXPLAIN оборачивает ровно одну инструкцию и не «протекает» вперёд/назад;
 *   - пустой/пробельный ввод.
 *
 * Инкрементальный feed_token-API не переносится: у нас его нет.
 */
final class StatementBoundaryTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * @param list<class-string<Statement>> $expected
     */
    private function assertStatementKinds(string $sql, array $expected): void
    {
        $script = $this->parser->parse($sql);

        self::assertCount(\count($expected), $script->statements, 'скрипт: ' . $sql);

        foreach ($expected as $i => $class) {
            self::assertInstanceOf($class, $script->statements[$i], "инструкция #{$i} в: {$sql}");
        }
    }

    public function testEmptyInputProducesNoStatements(): void
    {
        self::assertSame([], $this->parser->parse('')->statements);
    }

    /**
     * Порт `smoke.rs::whitespace_only_returns_done`.
     */
    public function testWhitespaceOnlyInputProducesNoStatements(): void
    {
        self::assertSame([], $this->parser->parse("\n\t  ")->statements);
        self::assertSame([], $this->parser->parse('   ')->statements);
        self::assertSame([], $this->parser->parse("\n\n")->statements);
    }

    /**
     * Порт `low_level.rs::batch_parse_bare_semicolons` /
     * `feed_tokens_bare_semicolons`.
     */
    public function testBareSemicolonsAreEmptyStatements(): void
    {
        $this->assertStatementKinds('; SELECT 1; ; SELECT 2; ;', [
            EmptyStatement::class,
            SelectStatement::class,
            EmptyStatement::class,
            SelectStatement::class,
            EmptyStatement::class,
        ]);
    }

    public function testLeadingAndTrailingBareSemicolons(): void
    {
        $this->assertStatementKinds('; SELECT 1; ;', [
            EmptyStatement::class,
            SelectStatement::class,
            EmptyStatement::class,
        ]);
    }

    public function testOnlyBareSemicolons(): void
    {
        $this->assertStatementKinds(';;;', [
            EmptyStatement::class,
            EmptyStatement::class,
            EmptyStatement::class,
        ]);
    }

    public function testTrailingSemicolonIsOptional(): void
    {
        $this->assertStatementKinds('SELECT 1', [SelectStatement::class]);
        $this->assertStatementKinds('SELECT 1;', [SelectStatement::class]);
    }

    public function testThreeStatementsWithoutTrailingSemicolon(): void
    {
        $this->assertStatementKinds('SELECT 1; SELECT 2; SELECT 3', [
            SelectStatement::class,
            SelectStatement::class,
            SelectStatement::class,
        ]);
    }

    /**
     * Порт `low_level.rs::feed_tokens_explain_then_normal` и
     * `batch_parse_explain_then_normal`.
     */
    public function testExplainDoesNotLeakIntoNextStatement(): void
    {
        $this->assertStatementKinds('EXPLAIN SELECT 1; SELECT 2', [
            ExplainStatement::class,
            SelectStatement::class,
        ]);

        $script = $this->parser->parse('EXPLAIN SELECT 1; SELECT 2');

        $explain = $script->statements[0];
        self::assertInstanceOf(ExplainStatement::class, $explain);
        self::assertFalse($explain->queryPlan);
        self::assertInstanceOf(SelectStatement::class, $explain->statement);
    }

    /**
     * Порт `low_level.rs::feed_tokens_normal_then_explain`:
     * EXPLAIN не должен «протекать назад».
     */
    public function testExplainDoesNotLeakIntoPreviousStatement(): void
    {
        $this->assertStatementKinds('SELECT 1; EXPLAIN SELECT 2', [
            SelectStatement::class,
            ExplainStatement::class,
        ]);
    }

    public function testExplainQueryPlanDoesNotLeakIntoNextStatement(): void
    {
        $script = $this->parser->parse('EXPLAIN QUERY PLAN SELECT 1; SELECT 2');

        self::assertCount(2, $script->statements);

        $explain = $script->statements[0];
        self::assertInstanceOf(ExplainStatement::class, $explain);
        self::assertTrue($explain->queryPlan);

        self::assertInstanceOf(SelectStatement::class, $script->statements[1]);
    }

    public function testExplainWrapsDmlStatement(): void
    {
        $script = $this->parser->parse('EXPLAIN INSERT INTO t (a) VALUES (1); SELECT 1');

        self::assertCount(2, $script->statements);

        $explain = $script->statements[0];
        self::assertInstanceOf(ExplainStatement::class, $explain);
        self::assertInstanceOf(InsertStatement::class, $explain->statement);
        self::assertInstanceOf(SelectStatement::class, $script->statements[1]);
    }

    public function testStatementOrderIsPreservedInMixedScript(): void
    {
        $this->assertStatementKinds(
            'BEGIN;'
            . 'CREATE TABLE t (id INTEGER);'
            . 'INSERT INTO t (id) VALUES (1);'
            . 'UPDATE t SET id = 2;'
            . 'SELECT * FROM t;'
            . 'DELETE FROM t;'
            . 'COMMIT;',
            [
                TransactionStatement::class,
                CreateTableStatement::class,
                InsertStatement::class,
                UpdateStatement::class,
                SelectStatement::class,
                DeleteStatement::class,
                TransactionStatement::class,
            ],
        );
    }

    public function testSemicolonInsideStringLiteralDoesNotSplitStatements(): void
    {
        $script = $this->parser->parse("SELECT 'a;b'; SELECT 2");

        self::assertCount(2, $script->statements);
        self::assertInstanceOf(SelectStatement::class, $script->statements[0]);
        self::assertInstanceOf(SelectStatement::class, $script->statements[1]);
    }

    public function testSemicolonInsideTriggerBodyDoesNotSplitScript(): void
    {
        $script = $this->parser->parse(
            'CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO u VALUES (1); END;'
            . 'SELECT 1;'
        );

        self::assertCount(2, $script->statements);
        self::assertInstanceOf(SelectStatement::class, $script->statements[1]);
    }
}