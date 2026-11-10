<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Исчерпывающие тесты DML и SELECT-запросов (комбинации всех ключевых фич):
 *   - SELECT: список колонок, FROM/JOIN, WHERE, GROUP BY, HAVING, ORDER BY, LIMIT,
 *     DISTINCT, UNION/INTERSECT/EXCEPT, CTE, оконные функции, скалярные подзапросы;
 *   - INSERT: VALUES / SELECT / DEFAULT VALUES, OR-actions, UPSERT, RETURNING;
 *   - UPDATE: одиночные и множественные присваивания, OR-actions, RETURNING;
 *   - DELETE: с/без WHERE, RETURNING;
 *   - утилиты: PRAGMA, ATTACH/DETACH, транзакции, SAVEPOINT, EXPLAIN.
 */
final class QueryCombinationsTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    // ========================================================================
    // SELECT — перечисление
    // ========================================================================

    /**
     * @dataProvider provideSelectQueries
     */
    public function testSelectParses(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
    }

    public static function provideSelectQueries(): iterable
    {
        // Базовые
        yield 'select 1' => ['SELECT 1'];
        yield 'select literal string' => ["SELECT 'hello'"];
        yield 'select star' => ['SELECT * FROM t'];
        yield 'select columns' => ['SELECT a, b, c FROM t'];
        yield 'select alias' => ['SELECT a AS x, b AS y FROM t'];
        yield 'select distinct' => ['SELECT DISTINCT a FROM t'];
        yield 'select all' => ['SELECT ALL a FROM t'];

        // Выражения в списке
        yield 'arith add' => ['SELECT a + b FROM t'];
        yield 'arith mul' => ['SELECT a * b FROM t'];
        yield 'arith mixed' => ['SELECT a + b * c FROM t'];
        yield 'paren expr' => ['SELECT (a + b) * c FROM t'];
        yield 'comparison' => ['SELECT a = b FROM t'];
        yield 'logical and' => ['SELECT a AND b FROM t'];
        yield 'logical or' => ['SELECT a OR b FROM t'];
        yield 'not expr' => ['SELECT NOT a FROM t'];
        yield 'cast' => ['SELECT CAST(x AS INTEGER) FROM t'];
        yield 'case simple' => ["SELECT CASE x WHEN 1 THEN 'one' ELSE 'other' END FROM t"];
        yield 'case searched' => ['SELECT CASE WHEN x > 0 THEN 1 WHEN x < 0 THEN -1 ELSE 0 END FROM t'];
        yield 'subquery scalar' => ['SELECT (SELECT 1) FROM t'];
        yield 'qualified column' => ['SELECT t.a FROM t'];

        // Функции
        yield 'count star' => ['SELECT COUNT(*) FROM t'];
        yield 'count distinct' => ['SELECT COUNT(DISTINCT a) FROM t'];
        yield 'sum' => ['SELECT SUM(x) FROM t'];
        yield 'avg' => ['SELECT AVG(x) FROM t'];
        yield 'min/max' => ['SELECT MIN(x), MAX(x) FROM t'];
        yield 'string function' => ["SELECT UPPER(name) FROM t"];
        yield 'function multi args' => ['SELECT SUBSTR(name, 1, 3) FROM t'];

        // FROM вариации
        yield 'from alias' => ['SELECT * FROM t AS t1'];
        yield 'from subquery' => ['SELECT * FROM (SELECT 1) AS s'];
        yield 'from subquery columns' => ['SELECT * FROM (SELECT 1, 2) AS s (a, b)'];

        // WHERE
        yield 'where eq' => ['SELECT * FROM t WHERE a = 1'];
        yield 'where neq' => ['SELECT * FROM t WHERE a <> 1'];
        yield 'where neq bang' => ['SELECT * FROM t WHERE a != 1'];
        yield 'where lt' => ['SELECT * FROM t WHERE a < 1'];
        yield 'where lte' => ['SELECT * FROM t WHERE a <= 1'];
        yield 'where gt' => ['SELECT * FROM t WHERE a > 1'];
        yield 'where gte' => ['SELECT * FROM t WHERE a >= 1'];
        yield 'where is null' => ['SELECT * FROM t WHERE a IS NULL'];
        yield 'where is not null' => ['SELECT * FROM t WHERE a IS NOT NULL'];
        yield 'where like' => ["SELECT * FROM t WHERE a LIKE 'x%'"];
        yield 'where not like' => ["SELECT * FROM t WHERE a NOT LIKE 'x%'"];
        yield 'where glob' => ["SELECT * FROM t WHERE a GLOB 'x*'"];
        yield 'where not glob' => ["SELECT * FROM t WHERE a NOT GLOB 'x*'"];
        yield 'where between' => ['SELECT * FROM t WHERE a BETWEEN 1 AND 10'];
        yield 'where not between' => ['SELECT * FROM t WHERE a NOT BETWEEN 1 AND 10'];
        yield 'where in list' => ['SELECT * FROM t WHERE a IN (1, 2, 3)'];
        yield 'where not in list' => ['SELECT * FROM t WHERE a NOT IN (1, 2, 3)'];
        yield 'where in subquery' => ['SELECT * FROM t WHERE a IN (SELECT a FROM u)'];
        yield 'where not in subquery' => ['SELECT * FROM t WHERE a NOT IN (SELECT a FROM u)'];
        yield 'where and' => ['SELECT * FROM t WHERE a = 1 AND b = 2'];
        yield 'where or' => ['SELECT * FROM t WHERE a = 1 OR b = 2'];
        yield 'where not' => ['SELECT * FROM t WHERE NOT a = 1'];
        yield 'where paren' => ['SELECT * FROM t WHERE (a = 1 OR b = 2) AND c > 0'];
        yield 'where complex chain' => ['SELECT * FROM t WHERE a = 1 AND b > 0 AND c < 100'];

        // GROUP BY / HAVING
        yield 'group by' => ['SELECT a, COUNT(*) FROM t GROUP BY a'];
        yield 'group by multiple' => ['SELECT a, b, COUNT(*) FROM t GROUP BY a, b'];
        yield 'group by having' => ['SELECT a, COUNT(*) FROM t GROUP BY a HAVING COUNT(*) > 1'];
        yield 'group by having complex' => ['SELECT a, SUM(b) FROM t GROUP BY a HAVING SUM(b) > 100 AND a > 0'];

        // ORDER BY / LIMIT
        yield 'order by' => ['SELECT * FROM t ORDER BY a'];
        yield 'order by asc' => ['SELECT * FROM t ORDER BY a ASC'];
        yield 'order by desc' => ['SELECT * FROM t ORDER BY a DESC'];
        yield 'order by multiple' => ['SELECT * FROM t ORDER BY a, b DESC'];
        yield 'limit' => ['SELECT * FROM t LIMIT 10'];
        yield 'limit offset' => ['SELECT * FROM t LIMIT 10 OFFSET 5'];
        yield 'order limit' => ['SELECT * FROM t ORDER BY a LIMIT 10'];
        yield 'order limit offset' => ['SELECT * FROM t ORDER BY a DESC LIMIT 10 OFFSET 20'];

        // JOIN
        yield 'inner join' => ['SELECT * FROM a JOIN b ON a.id = b.a_id'];
        yield 'inner join explicit' => ['SELECT * FROM a INNER JOIN b ON a.id = b.a_id'];
        yield 'left join' => ['SELECT * FROM a LEFT JOIN b ON a.id = b.a_id'];
        yield 'left outer join' => ['SELECT * FROM a LEFT OUTER JOIN b ON a.id = b.a_id'];
        yield 'right join' => ['SELECT * FROM a RIGHT JOIN b ON a.id = b.a_id'];
        yield 'right outer join' => ['SELECT * FROM a RIGHT OUTER JOIN b ON a.id = b.a_id'];
        yield 'full join' => ['SELECT * FROM a FULL JOIN b ON a.id = b.a_id'];
        yield 'full outer join' => ['SELECT * FROM a FULL OUTER JOIN b ON a.id = b.a_id'];
        yield 'cross join' => ['SELECT * FROM a CROSS JOIN b'];
        yield 'natural join' => ['SELECT * FROM a NATURAL JOIN b'];
        yield 'join using' => ['SELECT * FROM a JOIN b USING (id)'];
        yield 'join chain' => ['SELECT * FROM a JOIN b ON a.id = b.a_id JOIN c ON b.id = c.b_id'];
        yield 'join left chain' => ['SELECT * FROM a LEFT JOIN b ON a.id = b.a_id LEFT JOIN c ON b.id = c.b_id'];

        // UNION / INTERSECT / EXCEPT
        yield 'union' => ['SELECT 1 UNION SELECT 2'];
        yield 'union all' => ['SELECT 1 UNION ALL SELECT 2'];
        yield 'intersect' => ['SELECT 1 INTERSECT SELECT 2'];
        yield 'except' => ['SELECT 1 EXCEPT SELECT 2'];
        yield 'union order limit' => ['SELECT 1 UNION SELECT 2 ORDER BY 1 LIMIT 5'];
        yield 'union three' => ['SELECT 1 UNION SELECT 2 UNION ALL SELECT 3'];

        // CTE
        yield 'cte simple' => ['WITH c AS (SELECT 1) SELECT * FROM c'];
        yield 'cte two' => ['WITH a AS (SELECT 1), b AS (SELECT 2) SELECT * FROM a'];
        yield 'cte with columns' => ['WITH c (a, b) AS (SELECT 1, 2) SELECT * FROM c'];
        yield 'cte recursive' => ['WITH RECURSIVE r AS (SELECT 1) SELECT * FROM r'];
        yield 'cte with where' => ['WITH c AS (SELECT a FROM u WHERE a > 0) SELECT * FROM c WHERE b < 10'];

        // Оконные функции
        yield 'window partition' => ['SELECT SUM(x) OVER (PARTITION BY g) FROM t'];
        yield 'window order' => ['SELECT SUM(x) OVER (ORDER BY y) FROM t'];
        yield 'window partition order' => ['SELECT SUM(x) OVER (PARTITION BY g ORDER BY y) FROM t'];
        yield 'window row_number' => ['SELECT ROW_NUMBER() OVER (PARTITION BY g ORDER BY y) FROM t'];
        yield 'window alias' => ['SELECT RANK() OVER (ORDER BY x) AS r FROM t'];
    }

    // ========================================================================
    // INSERT
    // ========================================================================

    /**
     * @dataProvider provideInsertQueries
     */
    public function testInsertParses(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
    }

    public static function provideInsertQueries(): iterable
    {
        yield 'columns values' => ['INSERT INTO t (a) VALUES (1)'];
        yield 'no columns values' => ['INSERT INTO t VALUES (1, 2)'];
        yield 'multiple rows' => ['INSERT INTO t (a) VALUES (1), (2), (3)'];
        yield 'select body' => ['INSERT INTO t (a) SELECT a FROM u'];
        yield 'default values' => ['INSERT INTO t DEFAULT VALUES'];

        // OR-actions
        yield 'or replace' => ['INSERT OR REPLACE INTO t (a) VALUES (1)'];
        yield 'or ignore' => ['INSERT OR IGNORE INTO t (a) VALUES (1)'];
        yield 'or abort' => ['INSERT OR ABORT INTO t (a) VALUES (1)'];
        yield 'or fail' => ['INSERT OR FAIL INTO t (a) VALUES (1)'];
        yield 'or rollback' => ['INSERT OR ROLLBACK INTO t (a) VALUES (1)'];

        // RETURNING
        yield 'returning star' => ['INSERT INTO t (a) VALUES (1) RETURNING *'];
        yield 'returning column' => ['INSERT INTO t (a) VALUES (1) RETURNING id'];
        yield 'returning aliased' => ['INSERT INTO t (a) VALUES (1) RETURNING id AS new_id'];
        yield 'returning multiple' => ['INSERT INTO t (a) VALUES (1) RETURNING id, a'];

        // UPSERT
        yield 'upsert do nothing' => ['INSERT INTO t (a) VALUES (1) ON CONFLICT (a) DO NOTHING'];
        yield 'upsert do nothing no target' => ['INSERT INTO t (a) VALUES (1) ON CONFLICT DO NOTHING'];
        yield 'upsert do update' => ['INSERT INTO t (a, b) VALUES (1, 2) ON CONFLICT (a) DO UPDATE SET b = 3'];
        yield 'upsert do update where' => ['INSERT INTO t (a, b) VALUES (1, 2) ON CONFLICT (a) DO UPDATE SET b = 3 WHERE b < 2'];
        yield 'upsert returning' => ['INSERT INTO t (a) VALUES (1) ON CONFLICT (a) DO NOTHING RETURNING *'];
    }

    // ========================================================================
    // UPDATE
    // ========================================================================

    /**
     * @dataProvider provideUpdateQueries
     */
    public function testUpdateParses(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
    }

    public static function provideUpdateQueries(): iterable
    {
        yield 'simple' => ['UPDATE t SET a = 1'];
        yield 'with where' => ['UPDATE t SET a = 1 WHERE id = 5'];
        yield 'multiple assignments' => ['UPDATE t SET a = 1, b = 2, c = 3'];
        yield 'expression value' => ['UPDATE t SET a = a + 1'];
        yield 'string value' => ["UPDATE t SET a = 'x'"];
        yield 'or replace' => ['UPDATE OR REPLACE t SET a = 1'];
        yield 'or ignore' => ['UPDATE OR IGNORE t SET a = 1'];
        yield 'or abort' => ['UPDATE OR ABORT t SET a = 1'];
        yield 'returning star' => ['UPDATE t SET a = 1 RETURNING *'];
        yield 'returning column' => ['UPDATE t SET a = 1 RETURNING a'];
        yield 'returning aliased' => ['UPDATE t SET a = 1 RETURNING a AS new_a'];
        yield 'complex where' => ['UPDATE t SET a = 1 WHERE b > 0 AND c < 100'];
    }

    // ========================================================================
    // DELETE
    // ========================================================================

    /**
     * @dataProvider provideDeleteQueries
     */
    public function testDeleteParses(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
    }

    public static function provideDeleteQueries(): iterable
    {
        yield 'simple' => ['DELETE FROM t'];
        yield 'with where' => ['DELETE FROM t WHERE id = 1'];
        yield 'complex where' => ['DELETE FROM t WHERE a > 0 AND b < 10'];
        yield 'returning star' => ['DELETE FROM t RETURNING *'];
        yield 'returning column' => ['DELETE FROM t RETURNING id'];
        yield 'returning aliased' => ['DELETE FROM t RETURNING id AS deleted_id'];
        yield 'where returning' => ['DELETE FROM t WHERE id = 1 RETURNING *'];
    }

    // ========================================================================
    // Утилиты: PRAGMA, ATTACH, транзакции, SAVEPOINT, EXPLAIN
    // ========================================================================

    /**
     * @dataProvider provideUtilityQueries
     */
    public function testUtilityParses(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
    }

    public static function provideUtilityQueries(): iterable
    {
        // PRAGMA
        yield 'pragma bare' => ['PRAGMA user_version'];
        yield 'pragma equals' => ['PRAGMA foreign_keys = ON'];
        yield 'pragma equals int' => ['PRAGMA cache_size = 1000'];
        yield 'pragma function form' => ['PRAGMA cache_size(1000)'];
        yield 'pragma schema' => ['PRAGMA main.cache_size = 2000'];

        // ATTACH / DETACH
        yield 'attach' => ["ATTACH 'extra.db' AS extra"];
        yield 'attach database' => ["ATTACH DATABASE 'extra.db' AS extra"];
        yield 'detach' => ['DETACH extra'];
        yield 'detach database' => ['DETACH DATABASE extra'];

        // Транзакции
        yield 'begin' => ['BEGIN'];
        yield 'begin deferred' => ['BEGIN DEFERRED TRANSACTION'];
        yield 'begin immediate' => ['BEGIN IMMEDIATE TRANSACTION'];
        yield 'begin exclusive' => ['BEGIN EXCLUSIVE TRANSACTION'];
        yield 'commit' => ['COMMIT'];
        yield 'commit transaction' => ['COMMIT TRANSACTION'];
        yield 'end transaction' => ['END TRANSACTION'];
        yield 'rollback' => ['ROLLBACK'];
        yield 'rollback transaction' => ['ROLLBACK TRANSACTION'];
        yield 'rollback to savepoint' => ['ROLLBACK TO SAVEPOINT sp1'];
        yield 'rollback to' => ['ROLLBACK TO sp1'];

        // SAVEPOINT / RELEASE
        yield 'savepoint' => ['SAVEPOINT sp1'];
        yield 'release savepoint' => ['RELEASE SAVEPOINT sp1'];
        yield 'release short' => ['RELEASE sp1'];

        // EXPLAIN
        yield 'explain select' => ['EXPLAIN SELECT 1'];
        yield 'explain query plan' => ['EXPLAIN QUERY PLAN SELECT 1'];
        yield 'explain insert' => ['EXPLAIN INSERT INTO t (a) VALUES (1)'];
    }

    // ========================================================================
    // Многоинструкционные скрипты
    // ========================================================================

    /**
     * @dataProvider provideFullScripts
     */
    public function testFullScriptParses(string $sql, int $expectedCount): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount($expectedCount, $script->statements);
    }

    public static function provideFullScripts(): iterable
    {
        yield 'empty' => ['', 0];
        yield 'single semicolon' => [';', 1];
        yield 'create+insert+select' => [
            'CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL);'
            . 'INSERT INTO t (id, name) VALUES (1, \'a\');'
            . 'SELECT * FROM t;',
            3,
        ];
        yield 'create+schema with default current_timestamp' => [
            'CREATE TABLE t (id INTEGER PRIMARY KEY, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'INSERT INTO t DEFAULT VALUES;'
            . 'SELECT * FROM t WHERE created_at IS NOT NULL;',
            3,
        ];
        yield 'transaction insert commit' => [
            'BEGIN TRANSACTION; INSERT INTO t (a) VALUES (1); COMMIT;',
            3,
        ];
        yield 'pragma+select' => [
            'PRAGMA foreign_keys = ON; SELECT * FROM t;',
            2,
        ];
        yield 'ddl pipeline' => [
            'CREATE TABLE t (id INTEGER);'
            . 'CREATE INDEX idx_t_id ON t (id);'
            . 'DROP TABLE t;',
            3,
        ];
        yield 'multiple dml' => [
            'INSERT INTO t (a) VALUES (1);'
            . 'UPDATE t SET a = 2 WHERE a = 1;'
            . 'DELETE FROM t WHERE a = 2;',
            3,
        ];
    }
}