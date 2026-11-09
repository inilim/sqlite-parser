<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\FunctionCall;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Expression\Subquery;
use Inilim\SqliteParser\Ast\Expression\WindowFunction;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\JoinClause;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\WithClause;
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\TestCase;

/**
 * Сквозные тесты этапа 5 — продвинутый SELECT:
 * JOIN, GROUP BY / HAVING, DISTINCT, UNION, WITH (CTE), оконные функции.
 */
final class AdvancedSelectTest extends TestCase
{
    private SqliteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteParser();
    }

    private function parse(string $sql): Script
    {
        /** @var Script $script */
        $script = $this->parser->parse(StringSource::createFromString($sql));
        return $script;
    }

    private function select(string $sql): SelectStatement
    {
        $script = $this->parse($sql);
        $stmt = $script->statements[0] ?? null;
        self::assertInstanceOf(SelectStatement::class, $stmt, 'ожидался SelectStatement');
        return $stmt;
    }

    public function testDistinct(): void
    {
        $select = $this->select('SELECT DISTINCT a FROM t');
        self::assertTrue($select->distinct);
        self::assertCount(1, $select->columns);
    }

    public function testGroupByHaving(): void
    {
        $select = $this->select('SELECT a, COUNT(*) FROM t GROUP BY a HAVING COUNT(*) > 1');
        self::assertNotNull($select->groupBy);
        self::assertCount(1, $select->groupBy->expressions);
        self::assertNotNull($select->having);
    }

    public function testSimpleJoin(): void
    {
        $select = $this->select('SELECT * FROM a JOIN b ON a.id = b.a_id');
        self::assertNotNull($select->from);
        self::assertSame('a', $select->from->name);
        self::assertCount(1, $select->joins);

        /** @var JoinClause $join */
        $join = $select->joins[0];
        self::assertSame('', $join->type);
        self::assertNotNull($join->table);
        self::assertSame('b', $join->table->name);
        self::assertNotNull($join->on);
    }

    public function testLeftOuterJoin(): void
    {
        $select = $this->select('SELECT * FROM a LEFT OUTER JOIN b ON a.id = b.a_id');
        self::assertCount(1, $select->joins);
        self::assertSame('LEFT', $select->joins[0]->type);
    }

    public function testCrossJoin(): void
    {
        $select = $this->select('SELECT * FROM a CROSS JOIN b');
        self::assertCount(1, $select->joins);
        self::assertSame('CROSS', $select->joins[0]->type);
    }

    public function testJoinUsing(): void
    {
        $select = $this->select('SELECT * FROM a JOIN b USING (id)');
        self::assertCount(1, $select->joins);
        self::assertNotNull($select->joins[0]->using);
        self::assertSame(['id'], $select->joins[0]->using);
    }

    public function testUnion(): void
    {
        $script = $this->parse('SELECT 1 UNION SELECT 2');
        $stmt = $script->statements[0] ?? null;
        self::assertInstanceOf(CompoundSelect::class, $stmt);
        self::assertCount(2, $stmt->selects);
        self::assertSame(['UNION'], $stmt->operators);
    }

    public function testUnionAll(): void
    {
        $script = $this->parse('SELECT 1 UNION ALL SELECT 2');
        $stmt = $script->statements[0] ?? null;
        self::assertInstanceOf(CompoundSelect::class, $stmt);
        self::assertSame(['UNION ALL'], $stmt->operators);
    }

    public function testIntersect(): void
    {
        $script = $this->parse('SELECT 1 INTERSECT SELECT 2');
        $stmt = $script->statements[0] ?? null;
        self::assertInstanceOf(CompoundSelect::class, $stmt);
        self::assertSame(['INTERSECT'], $stmt->operators);
    }

    public function testWithCte(): void
    {
        $select = $this->select('WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertNotNull($select->with);
        self::assertInstanceOf(WithClause::class, $select->with);
        self::assertCount(1, $select->with->ctes);
        self::assertSame('c', $select->with->ctes[0]->name);
    }

    public function testWithRecursive(): void
    {
        $select = $this->select(
            'WITH RECURSIVE c AS (SELECT 1) SELECT * FROM c'
        );
        self::assertNotNull($select->with);
        self::assertTrue($select->with->recursive);
    }

    public function testDerivedTableInFrom(): void
    {
        $select = $this->select('SELECT * FROM (SELECT 1) AS t');
        self::assertNotNull($select->from);
        self::assertNotNull($select->from->subquery);
        self::assertSame('t', $select->from->alias);
    }

    public function testScalarSubquery(): void
    {
        $select = $this->select('SELECT (SELECT 1) AS x');
        self::assertCount(1, $select->columns);
        self::assertInstanceOf(Subquery::class, $select->columns[0]->expression);
    }

    public function testInSubquery(): void
    {
        $select = $this->select('SELECT * FROM t WHERE id IN (SELECT id FROM u)');
        self::assertNotNull($select->where);
        $where = $select->where;
        self::assertInstanceOf(
            \Inilim\SqliteParser\Ast\Expression\InExpression::class,
            $where,
        );
        self::assertNotNull($where->subquery);
        self::assertSame([], $where->list);
    }

    public function testWindowFunctionOver(): void
    {
        $select = $this->select('SELECT SUM(x) OVER (PARTITION BY g) AS s FROM t');
        self::assertCount(1, $select->columns);
        $expr = $select->columns[0]->expression;
        self::assertInstanceOf(WindowFunction::class, $expr);
        self::assertInstanceOf(FunctionCall::class, $expr->function);
        self::assertNotNull($expr->specification);
        self::assertNotNull($expr->specification->partitionBy);
        self::assertCount(1, $expr->specification->partitionBy);
    }

    public function testWindowFunctionOverOrderBy(): void
    {
        $select = $this->select('SELECT SUM(x) OVER (ORDER BY y) FROM t');
        $expr = $select->columns[0]->expression;
        self::assertInstanceOf(WindowFunction::class, $expr);
        self::assertNotNull($expr->specification);
        self::assertNotNull($expr->specification->orderBy);
    }

    public function testGroupByMultipleExpressions(): void
    {
        $select = $this->select('SELECT a, b, COUNT(*) FROM t GROUP BY a, b');
        self::assertNotNull($select->groupBy);
        self::assertCount(2, $select->groupBy->expressions);
    }

    public function testDistinctWithLiteral(): void
    {
        $select = $this->select('SELECT DISTINCT 1');
        self::assertTrue($select->distinct);
        self::assertCount(1, $select->columns);
        self::assertInstanceOf(Literal::class, $select->columns[0]->expression);
    }

    public function testJoinChain(): void
    {
        $select = $this->select(
            'SELECT * FROM a JOIN b ON a.id = b.a_id JOIN c ON b.id = c.b_id'
        );
        self::assertCount(2, $select->joins);
        self::assertSame('b', $select->joins[0]->table->name);
        self::assertSame('c', $select->joins[1]->table->name);
    }

    public function testSubqueryInFromWithColumnAliases(): void
    {
        $select = $this->select('SELECT * FROM (SELECT 1, 2) AS t (a, b)');
        self::assertNotNull($select->from->subquery);
        self::assertSame('t', $select->from->alias);
        self::assertSame(['a', 'b'], $select->from->columnAliases);
    }

    public function testMultiSelectThroughWhere(): void
    {
        $select = $this->select('SELECT a FROM t WHERE a = 1 GROUP BY a HAVING a > 0');
        self::assertNotNull($select->where);
        self::assertNotNull($select->groupBy);
        self::assertNotNull($select->having);
        self::assertInstanceOf(
            \Inilim\SqliteParser\Ast\Expression\BinaryOperation::class,
            $select->where,
        );
    }

    public function testColumnReferenceInWhere(): void
    {
        $select = $this->select('SELECT 1 FROM t WHERE t.id = 1');
        self::assertNotNull($select->where);
        $where = $select->where;
        self::assertInstanceOf(
            \Inilim\SqliteParser\Ast\Expression\BinaryOperation::class,
            $where,
        );
        self::assertInstanceOf(ColumnReference::class, $where->left);
        self::assertSame('id', $where->left->name);
        self::assertSame('t', $where->left->table);
    }
}