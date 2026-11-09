<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\TestCase;

/**
 * Сквозные тесты SELECT (этап 3 плана).
 *
 * Каждый тест парсит SQL-строку через сгенерированный SqliteParser
 * и проверяет типизированное AST.
 */
final class SelectTest extends TestCase
{
    private function parse(string $sql): Script
    {
        /** @var Script */
        return (new SqliteParser())->parse(StringSource::createFromString($sql));
    }

    public function testSelectLiteral(): void
    {
        $script = $this->parse('SELECT 1');

        self::assertCount(1, $script->statements);

        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        self::assertCount(1, $stmt->columns);

        $col = $stmt->columns[0];
        self::assertInstanceOf(ResultColumn::class, $col);
        self::assertNull($col->alias);

        self::assertInstanceOf(Literal::class, $col->expression);
        self::assertSame(1, $col->expression->value);
        self::assertSame('int', $col->expression->kind);

        self::assertNull($stmt->from);
        self::assertNull($stmt->where);
        self::assertNull($stmt->orderBy);
        self::assertNull($stmt->limit);
    }

    public function testSelectStarFrom(): void
    {
        $script = $this->parse('SELECT * FROM users');

        self::assertCount(1, $script->statements);

        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        self::assertCount(1, $stmt->columns);

        $col = $stmt->columns[0];
        self::assertInstanceOf(ResultColumn::class, $col);
        self::assertNull($col->alias);
        self::assertInstanceOf(Star::class, $col->expression);

        self::assertInstanceOf(TableReference::class, $stmt->from);
        self::assertSame('users', $stmt->from->name);
        self::assertNull($stmt->from->alias);
    }

    public function testSelectColumnsWithWhere(): void
    {
        $script = $this->parse('SELECT id, name FROM users WHERE id = 1');

        self::assertCount(1, $script->statements);

        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        self::assertCount(2, $stmt->columns);

        $col0 = $stmt->columns[0];
        self::assertInstanceOf(ColumnReference::class, $col0->expression);
        self::assertSame('id', $col0->expression->name);

        $col1 = $stmt->columns[1];
        self::assertInstanceOf(ColumnReference::class, $col1->expression);
        self::assertSame('name', $col1->expression->name);

        self::assertInstanceOf(TableReference::class, $stmt->from);
        self::assertSame('users', $stmt->from->name);

        self::assertInstanceOf(BinaryOperation::class, $stmt->where);
        self::assertSame('=', $stmt->where->operator);
        self::assertInstanceOf(ColumnReference::class, $stmt->where->left);
        self::assertSame('id', $stmt->where->left->name);
        self::assertInstanceOf(Literal::class, $stmt->where->right);
        self::assertSame(1, $stmt->where->right->value);
    }
}