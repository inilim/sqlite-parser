<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Комбинаторные тесты парсера.
 *
 * Вместо ручного перечисления похожих SQL-запросов используем декартовы
 * произведения строительных блоков прямо в data providers на генераторах.
 * Это даёт широкое покрытие грамматики при минимальном коде.
 *
 * Все проверки — это "запрос парсится ровно в N statements без исключений".
 * Семантика (существует ли таблица/колонка) не проверяется — только синтаксис.
 */
final class CombinatoricsTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    private function assertSingleStatement(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(
            1,
            $script->statements,
            "Ожидали ровно 1 statement, но получили " . \count($script->statements) . " в: {$sql}",
        );
    }

    // ========================================================================
    // 1. SELECT: DISTINCT × columns × FROM × WHERE × GROUP BY × ORDER BY × LIMIT
    // ========================================================================

    /**
     * @dataProvider provideSelectClauseCombinations
     */
    public function testSelectClauseCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideSelectClauseCombinations(): iterable
    {
        $distincts = ['', 'DISTINCT '];
        $columns   = ['*', 'a', 'a, b'];
        $froms     = [' FROM t', ' FROM t AS t1'];
        $wheres    = ['', ' WHERE a = 1'];
        $groups    = ['', ' GROUP BY a'];
        $orders    = ['', ' ORDER BY a DESC'];
        $limits    = ['', ' LIMIT 10'];

        foreach ($distincts as $d) {
            foreach ($columns as $c) {
                foreach ($froms as $f) {
                    foreach ($wheres as $w) {
                        foreach ($groups as $g) {
                            foreach ($orders as $o) {
                                foreach ($limits as $l) {
                                    yield ['SELECT ' . $d . $c . $f . $w . $g . $o . $l];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ========================================================================
    // 2. LIMIT / OFFSET в разных комбинациях
    // ========================================================================

    /**
     * @dataProvider provideLimitOffsetCombinations
     */
    public function testLimitOffsetCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideLimitOffsetCombinations(): iterable
    {
        $limits = ['LIMIT 1', 'LIMIT 10', 'LIMIT 10 OFFSET 0', 'LIMIT 10 OFFSET 5'];
        $orders = ['', ' ORDER BY a'];

        foreach ($limits as $lim) {
            foreach ($orders as $ord) {
                yield ["SELECT * FROM t{$ord} {$lim}"];
            }
        }
    }

    // ========================================================================
    // 3. JOIN: тип × ON-условие
    // ========================================================================

    /**
     * @dataProvider provideJoinCombinations
     */
    public function testJoinCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJoinCombinations(): iterable
    {
        $types = ['JOIN', 'INNER JOIN', 'LEFT JOIN', 'LEFT OUTER JOIN'];
        $ons = [
            'a.id = b.a_id',
            'a.id = b.a_id AND b.x > 0',
        ];

        foreach ($types as $type) {
            foreach ($ons as $on) {
                yield ["SELECT * FROM a {$type} b ON {$on}"];
            }
        }

        yield ['SELECT * FROM a CROSS JOIN b'];
        yield ['SELECT * FROM a NATURAL JOIN b'];
        yield ['SELECT * FROM a JOIN b USING (id)'];
        yield ['SELECT * FROM a JOIN b ON a.id = b.a_id LEFT JOIN c ON b.id = c.b_id'];
        yield ['SELECT * FROM a LEFT JOIN b ON a.id = b.a_id LEFT JOIN c ON b.id = c.b_id'];
    }

    // ========================================================================
    // 4. Бинарные операторы × операнды
    // ========================================================================

    /**
     * @dataProvider provideBinaryOperators
     */
    public function testBinaryOperators(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideBinaryOperators(): iterable
    {
        $operators = [
            '+', '-', '*', '/', '%',
            '=', '<>', '!=', '<', '<=', '>', '>=',
            'AND', 'OR', '||',
        ];
        $operands = ['a', '1', 't.x'];

        foreach ($operators as $op) {
            foreach ($operands as $left) {
                foreach ($operands as $right) {
                    yield ["SELECT {$left} {$op} {$right} FROM t"];
                }
            }
        }
    }

    // ========================================================================
    // 5. Постфиксные операторы (IS NULL, LIKE, GLOB, BETWEEN, IN)
    // ========================================================================

    /**
     * @dataProvider providePostfixOperators
     */
    public function testPostfixOperators(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function providePostfixOperators(): iterable
    {
        $exprs = ['x', 't.x', 'x + 1'];
        $postfixes = [
            'IS NULL',
            'IS NOT NULL',
            "LIKE 'A%'",
            "NOT LIKE 'A%'",
            "GLOB 'A*'",
            "NOT GLOB 'A*'",
            'BETWEEN 1 AND 10',
            'NOT BETWEEN 1 AND 10',
            'IN (1, 2, 3)',
            'NOT IN (1, 2, 3)',
        ];

        foreach ($exprs as $e) {
            foreach ($postfixes as $p) {
                yield ["SELECT {$e} {$p} FROM t"];
            }
        }
    }

    // ========================================================================
    // 6. Функции
    // ========================================================================

    /**
     * @dataProvider provideFunctionCalls
     */
    public function testFunctionCalls(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideFunctionCalls(): iterable
    {
        $calls = [
            'COUNT(*)',
            'COUNT(DISTINCT x)',
            'SUM(x)',
            'AVG(x)',
            'MIN(x)',
            'MAX(x)',
            'UPPER(name)',
            'LOWER(name)',
            'LENGTH(name)',
            'SUBSTR(name, 1, 3)',
            'COALESCE(a, b, c)',
            'ROUND(x, 2)',
            'ABS(x)',
            'IFNULL(a, b)',
            'NULLIF(a, b)',
            'TRIM(x)',
        ];

        foreach ($calls as $c) {
            yield ["SELECT {$c} FROM t"];
        }
    }

    // ========================================================================
    // 7. Оконные функции: function × specification
    // ========================================================================

    /**
     * @dataProvider provideWindowFunctions
     */
    public function testWindowFunctions(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideWindowFunctions(): iterable
    {
        $functions = [
            'SUM(x)',
            'AVG(x)',
            'COUNT(*)',
            'ROW_NUMBER()',
            'RANK()',
            'DENSE_RANK()',
        ];
        $specs = [
            'PARTITION BY g',
            'ORDER BY y',
            'PARTITION BY g ORDER BY y',
            '',
        ];

        foreach ($functions as $f) {
            foreach ($specs as $s) {
                $over = $s === '' ? '()' : "({$s})";
                yield ["SELECT {$f} OVER {$over} FROM t"];
            }
        }
    }

    // ========================================================================
    // 8. DDL: тип колонки × constraint
    // ========================================================================

    /**
     * @dataProvider provideColumnDefinitions
     */
    public function testColumnDefinitions(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideColumnDefinitions(): iterable
    {
        $types = [
            'INTEGER', 'INT', 'TEXT', 'REAL', 'BLOB', 'NUMERIC',
            'BOOLEAN', 'VARCHAR(255)', 'DECIMAL(10,2)', 'DATETIME',
        ];
        $constraints = [
            '',
            'NOT NULL',
            'UNIQUE',
            'PRIMARY KEY',
            'NOT NULL UNIQUE',
            'DEFAULT 0',
            "DEFAULT 'x'",
            'DEFAULT CURRENT_TIMESTAMP',
            'NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'CHECK (c > 0)',
            'NOT NULL CHECK (c > 0)',
            'COLLATE NOCASE',
        ];

        foreach ($types as $t) {
            foreach ($constraints as $c) {
                $def = $c === '' ? "c {$t}" : "c {$t} {$c}";
                yield ["CREATE TABLE t ({$def});"];
            }
        }
    }

    // ========================================================================
    // 9. INSERT: OR-действие × тело × RETURNING
    // ========================================================================

    /**
     * @dataProvider provideInsertCombinations
     */
    public function testInsertCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideInsertCombinations(): iterable
    {
        $orActions = [
            '',
            'OR REPLACE ',
            'OR IGNORE ',
            'OR ABORT ',
            'OR FAIL ',
            'OR ROLLBACK ',
        ];
        $bodiesWithReturning = [
            'INTO t (a) VALUES (1)',
            'INTO t (a) VALUES (1), (2)',
            'INTO t (a) SELECT 1',
        ];
        $returns = [
            '',
            ' RETURNING *',
            ' RETURNING id',
            ' RETURNING id AS new_id',
        ];

        foreach ($orActions as $or) {
            foreach ($bodiesWithReturning as $body) {
                foreach ($returns as $r) {
                    yield ["INSERT {$or}{$body}{$r}"];
                }
            }
        }

        // DEFAULT VALUES — без RETURNING (в SQLite RETURNING для этой формы не поддержан)
        foreach ($orActions as $or) {
            yield ["INSERT {$or}INTO t DEFAULT VALUES"];
        }
    }

    // ========================================================================
    // 10. UPDATE: OR-действие × SET × WHERE × RETURNING
    // ========================================================================

    /**
     * @dataProvider provideUpdateCombinations
     */
    public function testUpdateCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideUpdateCombinations(): iterable
    {
        $orActions = ['', 'OR REPLACE ', 'OR IGNORE ', 'OR ABORT '];
        $assignments = [
            'a = 1',
            'a = 1, b = 2',
            'a = a + 1',
            "a = 'x'",
        ];
        $wheres = ['', ' WHERE id = 1'];
        $returns = ['', ' RETURNING *'];

        foreach ($orActions as $or) {
            foreach ($assignments as $as) {
                foreach ($wheres as $w) {
                    foreach ($returns as $r) {
                        yield ["UPDATE {$or}t SET {$as}{$w}{$r}"];
                    }
                }
            }
        }
    }

    // ========================================================================
    // 11. DELETE: WHERE × RETURNING
    // ========================================================================

    /**
     * @dataProvider provideDeleteCombinations
     */
    public function testDeleteCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideDeleteCombinations(): iterable
    {
        $wheres = ['', ' WHERE id = 1', ' WHERE a > 0 AND b < 10'];
        $returns = ['', ' RETURNING *', ' RETURNING id'];

        foreach ($wheres as $w) {
            foreach ($returns as $r) {
                yield ["DELETE FROM t{$w}{$r}"];
            }
        }
    }

    // ========================================================================
    // 12. Составные SELECT (UNION / INTERSECT / EXCEPT)
    // ========================================================================

    /**
     * @dataProvider provideCompoundSelects
     */
    public function testCompoundSelects(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideCompoundSelects(): iterable
    {
        $operators = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT'];
        $selects = [
            'SELECT 1',
            'SELECT a FROM t',
            'SELECT a, b FROM t WHERE a > 0',
        ];

        foreach ($operators as $op) {
            foreach ($selects as $s1) {
                foreach ($selects as $s2) {
                    yield ["{$s1} {$op} {$s2}"];
                }
            }
        }
    }

    // ========================================================================
    // 13. CTE: RECURSIVE × форма CTE
    // ========================================================================

    /**
     * @dataProvider provideCteCombinations
     */
    public function testCteCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideCteCombinations(): iterable
    {
        $recursive = ['', 'RECURSIVE '];
        $cteDefs = [
            'c AS (SELECT 1)',
            'c (a) AS (SELECT 1)',
            'c AS (SELECT a FROM u)',
            'a AS (SELECT 1), b AS (SELECT 2)',
        ];

        foreach ($recursive as $r) {
            foreach ($cteDefs as $cte) {
                yield ["WITH {$r}{$cte} SELECT * FROM c"];
            }
        }

        yield ['WITH c AS (SELECT 1) SELECT c.a FROM c'];
        yield ['WITH c AS (SELECT a FROM u) SELECT * FROM c WHERE a > 0'];
    }

    // ========================================================================
    // 14. EXPLAIN [QUERY PLAN]
    // ========================================================================

    /**
     * @dataProvider provideExplainCombinations
     */
    public function testExplainCombinations(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideExplainCombinations(): iterable
    {
        $queries = [
            'SELECT 1',
            'SELECT * FROM t',
            'INSERT INTO t (a) VALUES (1)',
        ];
        $explains = ['EXPLAIN', 'EXPLAIN QUERY PLAN'];

        foreach ($explains as $e) {
            foreach ($queries as $q) {
                yield ["{$e} {$q}"];
            }
        }
    }

    // ========================================================================
    // 15. Транзакции и SAVEPOINT
    // ========================================================================

    /**
     * @dataProvider provideTransactionStatements
     */
    public function testTransactionStatements(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideTransactionStatements(): iterable
    {
        $statements = [
            'BEGIN',
            'BEGIN DEFERRED TRANSACTION',
            'BEGIN IMMEDIATE TRANSACTION',
            'BEGIN EXCLUSIVE TRANSACTION',
            'COMMIT',
            'COMMIT TRANSACTION',
            'END TRANSACTION',
            'ROLLBACK',
            'ROLLBACK TRANSACTION',
            'ROLLBACK TO SAVEPOINT sp1',
            'ROLLBACK TO sp1',
            'SAVEPOINT sp1',
            'RELEASE sp1',
            'RELEASE SAVEPOINT sp1',
        ];

        foreach ($statements as $s) {
            yield [$s];
        }
    }

    // ========================================================================
    // 16. Полный скрипт: все пары statements через `;`
    // ========================================================================

    /**
     * @dataProvider provideFullScriptPairs
     */
    public function testFullScriptPairs(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(
            2,
            $script->statements,
            "Ожидали 2 statements в: {$sql}",
        );
    }

    public static function provideFullScriptPairs(): iterable
    {
        $statements = [
            'CREATE TABLE t (id INTEGER)',
            'CREATE INDEX i ON t (id)',
            "INSERT INTO t (id) VALUES (1)",
            'UPDATE t SET id = 2',
            'DELETE FROM t',
            'SELECT * FROM t',
            'PRAGMA foreign_keys = ON',
            'BEGIN',
            'COMMIT',
        ];

        foreach ($statements as $s1) {
            foreach ($statements as $s2) {
                yield ["{$s1}; {$s2};"];
            }
        }
    }
}