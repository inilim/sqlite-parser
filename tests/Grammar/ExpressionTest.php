<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\BetweenExpression;
use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\CaseExpression;
use Inilim\SqliteParser\Ast\Expression\CastExpression;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\FunctionCall;
use Inilim\SqliteParser\Ast\Expression\InExpression;
use Inilim\SqliteParser\Ast\Expression\IsNullExpression;
use Inilim\SqliteParser\Ast\Expression\LikeExpression;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Ast\Expression\UnaryOperation;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\TestCase;

/**
 * Тесты полноценной грамматики выражений (этап 4).
 *
 * Проверяют приоритеты, ассоциативность, поддержку функций, CAST, CASE,
 * а также постфиксные операторы (IS NULL, LIKE, GLOB, BETWEEN, IN).
 */
final class ExpressionTest extends TestCase
{
    private SqliteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteParser();
    }

    /**
     * @return list<ResultColumn>
     */
    private function parseSelectList(string $sql): array
    {
        $ast = $this->parser->parse(StringSource::createFromString($sql));
        self::assertSame(1, \count($ast->statements));

        $select = $ast->statements[0];
        self::assertInstanceOf(SelectStatement::class, $select);

        /** @var SelectStatement $select */
        return $select->columns;
    }

    public function testAdditiveAndMultiplicativePrecedence(): void
    {
        $columns = $this->parseSelectList('SELECT 1 + 2 * 3');
        self::assertCount(1, $columns);

        $expr = $columns[0]->expression;
        self::assertInstanceOf(BinaryOperation::class, $expr);
        self::assertSame('+', $expr->operator);

        self::assertInstanceOf(Literal::class, $expr->left);
        self::assertSame(1, $expr->left->value);

        self::assertInstanceOf(BinaryOperation::class, $expr->right);
        self::assertSame('*', $expr->right->operator);
        self::assertInstanceOf(Literal::class, $expr->right->left);
        self::assertInstanceOf(Literal::class, $expr->right->right);
        self::assertSame(2, $expr->right->left->value);
        self::assertSame(3, $expr->right->right->value);
    }

    public function testParenthesizedExpression(): void
    {
        $columns = $this->parseSelectList('SELECT (1 + 2) * 3');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(BinaryOperation::class, $expr);
        self::assertSame('*', $expr->operator);

        self::assertInstanceOf(BinaryOperation::class, $expr->left);
        self::assertSame('+', $expr->left->operator);

        self::assertInstanceOf(Literal::class, $expr->right);
        self::assertSame(3, $expr->right->value);
    }

    public function testAndOrPrecedence(): void
    {
        $columns = $this->parseSelectList('SELECT a = 1 AND b <> 2');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(BinaryOperation::class, $expr);
        self::assertSame('AND', $expr->operator);

        self::assertInstanceOf(BinaryOperation::class, $expr->left);
        self::assertSame('=', $expr->left->operator);

        self::assertInstanceOf(BinaryOperation::class, $expr->right);
        self::assertSame('<>', $expr->right->operator);
    }

    public function testUnaryNot(): void
    {
        $columns = $this->parseSelectList('SELECT NOT a = 1');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(UnaryOperation::class, $expr);
        self::assertSame('NOT', $expr->operator);

        self::assertInstanceOf(BinaryOperation::class, $expr->operand);
        self::assertSame('=', $expr->operand->operator);
    }

    public function testUnaryMinus(): void
    {
        $columns = $this->parseSelectList('SELECT -5');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(UnaryOperation::class, $expr);
        self::assertSame('-', $expr->operator);
        self::assertInstanceOf(Literal::class, $expr->operand);
        self::assertSame(5, $expr->operand->value);
    }

    public function testCastExpression(): void
    {
        $columns = $this->parseSelectList('SELECT CAST(x AS INTEGER)');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(CastExpression::class, $expr);
        self::assertSame('INTEGER', $expr->type);
        self::assertInstanceOf(ColumnReference::class, $expr->expression);
        self::assertSame('x', $expr->expression->name);
    }

    public function testCountStarFunctionCall(): void
    {
        $columns = $this->parseSelectList('SELECT COUNT(*)');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('COUNT', \strtoupper($expr->name));
        self::assertFalse($expr->distinct);
        self::assertCount(1, $expr->arguments);
        self::assertInstanceOf(Star::class, $expr->arguments[0]);
    }

    public function testCountDistinctFunctionCall(): void
    {
        $columns = $this->parseSelectList('SELECT COUNT(DISTINCT x)');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('COUNT', \strtoupper($expr->name));
        self::assertTrue($expr->distinct);
        self::assertCount(1, $expr->arguments);
        self::assertInstanceOf(ColumnReference::class, $expr->arguments[0]);
        self::assertSame('x', $expr->arguments[0]->name);
    }

    public function testSimpleCaseExpression(): void
    {
        $columns = $this->parseSelectList(
            'SELECT CASE x WHEN 1 THEN \'one\' ELSE \'other\' END'
        );
        $expr = $columns[0]->expression;

        self::assertInstanceOf(CaseExpression::class, $expr);
        self::assertNotNull($expr->subject);
        self::assertInstanceOf(ColumnReference::class, $expr->subject);
        self::assertCount(1, $expr->whens);
        self::assertNotNull($expr->else);
    }

    public function testSearchedCaseExpression(): void
    {
        $columns = $this->parseSelectList(
            'SELECT CASE WHEN x > 0 THEN 1 WHEN x < 0 THEN -1 ELSE 0 END'
        );
        $expr = $columns[0]->expression;

        self::assertInstanceOf(CaseExpression::class, $expr);
        self::assertNull($expr->subject);
        self::assertCount(2, $expr->whens);
        self::assertNotNull($expr->else);
    }

    public function testIsNullExpression(): void
    {
        $columns = $this->parseSelectList('SELECT x IS NULL');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(IsNullExpression::class, $expr);
        self::assertFalse($expr->not);
        self::assertInstanceOf(ColumnReference::class, $expr->expression);
    }

    public function testIsNotNullExpression(): void
    {
        $columns = $this->parseSelectList('SELECT x IS NOT NULL');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(IsNullExpression::class, $expr);
        self::assertTrue($expr->not);
    }

    public function testLikeExpression(): void
    {
        $columns = $this->parseSelectList('SELECT name LIKE \'A%\'');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(LikeExpression::class, $expr);
        self::assertFalse($expr->not);
        self::assertFalse($expr->glob);
        self::assertInstanceOf(ColumnReference::class, $expr->expression);
        self::assertInstanceOf(Literal::class, $expr->pattern);
    }

    public function testNotGlobExpression(): void
    {
        $columns = $this->parseSelectList('SELECT name NOT GLOB \'A*\'');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(LikeExpression::class, $expr);
        self::assertTrue($expr->not);
        self::assertTrue($expr->glob);
    }

    public function testBetweenExpression(): void
    {
        $columns = $this->parseSelectList('SELECT x BETWEEN 1 AND 10');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(BetweenExpression::class, $expr);
        self::assertFalse($expr->not);
        self::assertInstanceOf(ColumnReference::class, $expr->expression);
        self::assertInstanceOf(Literal::class, $expr->lower);
        self::assertInstanceOf(Literal::class, $expr->upper);
        self::assertSame(1, $expr->lower->value);
        self::assertSame(10, $expr->upper->value);
    }

    public function testBetweenThenAnd(): void
    {
        $columns = $this->parseSelectList('SELECT x BETWEEN 1 AND 10 AND y = 2');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(BinaryOperation::class, $expr);
        self::assertSame('AND', $expr->operator);
        self::assertInstanceOf(BetweenExpression::class, $expr->left);
        self::assertInstanceOf(BinaryOperation::class, $expr->right);
        self::assertSame('=', $expr->right->operator);
    }

    public function testInExpression(): void
    {
        $columns = $this->parseSelectList('SELECT x IN (1, 2, 3)');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(InExpression::class, $expr);
        self::assertFalse($expr->not);
        self::assertCount(3, $expr->list);
        foreach ($expr->list as $item) {
            self::assertInstanceOf(Literal::class, $item);
        }
    }

    public function testNotInExpression(): void
    {
        $columns = $this->parseSelectList('SELECT x NOT IN (1, 2)');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(InExpression::class, $expr);
        self::assertTrue($expr->not);
        self::assertCount(2, $expr->list);
    }

    public function testQualifiedColumnReference(): void
    {
        $columns = $this->parseSelectList('SELECT t.id FROM t');
        $expr = $columns[0]->expression;

        self::assertInstanceOf(ColumnReference::class, $expr);
        self::assertSame('id', $expr->name);
        self::assertSame('t', $expr->table);
    }

    public function testChainedComparisonIsLeftAssociative(): void
    {
        $columns = $this->parseSelectList('SELECT a = b = c');
        $expr = $columns[0]->expression;

        // Левая ассоциативность: ((a = b) = c)
        self::assertInstanceOf(BinaryOperation::class, $expr);
        self::assertSame('=', $expr->operator);
        self::assertInstanceOf(BinaryOperation::class, $expr->left);
        self::assertSame('=', $expr->left->operator);
    }
}