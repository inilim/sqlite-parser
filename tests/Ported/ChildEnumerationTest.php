<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Printer\NodeDumper;
use Inilim\SqliteParser\Traversal\NodeChildExtractor;
use Inilim\SqliteParser\Traversal\NodeFinder;
use PHPUnit\Framework\TestCase;

/**
 * Порт `child_enumeration.rs`.
 *
 * В Rust `AnyParsedStatement::child_node_ids` автоматически раскрывает
 * списки: для SelectStmt с ResultColumnList возвращаются сами элементы
 * списка, а не узел списка.
 *
 * У нас роль этого API играет `NodeChildExtractor::children()`, а списки
 * колонок представлены обычным массивом `ResultColumn[]`.
 */
final class ChildEnumerationTest extends TestCase
{
    private function select(string $sql): SelectStatement
    {
        $script = (new Parser())->parse($sql);
        self::assertCount(1, $script->statements);

        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        return $stmt;
    }

    public function testSelectChildrenAreNodes(): void
    {
        $select = $this->select('SELECT a, b FROM t');

        $children = NodeChildExtractor::children($select);

        self::assertNotEmpty($children, 'SelectStatement должен иметь хотя бы одного ребёнка');

        foreach ($children as $child) {
            self::assertInstanceOf(Node::class, $child);
        }
    }

    public function testSelectChildrenContainTableReference(): void
    {
        $select = $this->select('SELECT a, b FROM t');

        $found = false;

        foreach (NodeChildExtractor::children($select) as $child) {
            if ($child instanceof TableReference) {
                $found = true;
            }
        }

        self::assertTrue($found, 'среди детей SelectStatement ожидался TableReference');
    }

    public function testResultColumnsAreEnumeratedForTwoColumns(): void
    {
        $select = $this->select('SELECT a, b FROM t');

        $count = 0;

        foreach (NodeChildExtractor::children($select) as $child) {
            if ($child instanceof ResultColumn) {
                $count++;
            }
        }

        self::assertSame(2, $count, 'список колонок должен раскрываться в отдельных детей');
    }

    public function testResultColumnsAreEnumeratedForThreeColumns(): void
    {
        $select = $this->select('SELECT a, b, c FROM t');

        $count = 0;

        foreach (NodeChildExtractor::children($select) as $child) {
            if ($child instanceof ResultColumn) {
                $count++;
            }
        }

        self::assertSame(3, $count);
    }

    public function testNodeFinderFindsAllResultColumns(): void
    {
        $select = $this->select('SELECT a, b, c FROM t');

        $columns = (new NodeFinder(ResultColumn::class))->findAll($select);

        self::assertCount(3, $columns);

        foreach ($columns as $column) {
            self::assertInstanceOf(ResultColumn::class, $column);
        }
    }

    public function testResultColumnHasExpressionChild(): void
    {
        $select = $this->select('SELECT a FROM t');

        $column = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);

        $found = false;

        foreach (NodeChildExtractor::children($column) as $child) {
            if ($child instanceof ColumnReference) {
                $found = true;
            }
        }

        self::assertTrue($found, 'ResultColumn должен содержать выражение-ребёнка');
    }

    public function testFinderLocatesNestedColumnReferences(): void
    {
        $select = $this->select('SELECT a FROM t WHERE b = 1');

        $refs = (new NodeFinder(ColumnReference::class))->findAll($select);

        $names = [];

        foreach ($refs as $ref) {
            self::assertInstanceOf(ColumnReference::class, $ref);
            $names[] = $ref->name;
        }

        self::assertContains('a', $names);
        self::assertContains('b', $names);
    }

    public function testFinderReturnsNullWhenNothingMatches(): void
    {
        $select = $this->select('SELECT 1');

        $first = (new NodeFinder(TableReference::class))->findFirst($select);

        self::assertNull($first);
    }

    /**
     * Аналог `dump_star_column_flags`: человекочитаемый дамп отражает
     * фактическую структуру дерева.
     */
    public function testDumperShowsStatementAndExpressionStructure(): void
    {
        $select = $this->select('SELECT id FROM t');

        $dump = (new NodeDumper())->dump($select);

        self::assertNotSame('', \trim($dump));
        self::assertStringContainsString('SelectStatement', $dump);
        self::assertStringContainsString('ColumnReference', $dump);
    }
}