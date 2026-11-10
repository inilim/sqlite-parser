<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\FunctionCall;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Тесты работы с JSON (SQLite JSON1 extension).
 *
 * Покрывают:
 *   - конструкторы JSON: json(), json_object(), json_array(), json_quote(), json_patch();
 *   - извлечение и проверку: json_extract(), json_type(), json_valid(),
 *     json_array_length(), json_error_position();
 *   - мутации: json_insert(), json_replace(), json_set(), json_remove();
 *   - агрегаты: json_group_array(), json_group_object();
 *   - JSON-контексты: SELECT-список, WHERE, GROUP BY/HAVING, ORDER BY,
 *     INSERT/UPDATE/DELETE RETURNING, CTE, UNION, вложенные вызовы,
 *     комбинация с операторами/CAST/CASE/LIKE/BETWEEN/IN.
 *
 * Все проверки — «запрос парсится ровно в 1 statement без исключений»,
 * плюс отдельные проверки формы AST для ключевых JSON-конструкций.
 */
final class JsonTest extends TestCase
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
            "Ожидали ровно 1 statement в: {$sql}",
        );
    }

    // ========================================================================
    // 1. Конструкторы JSON: json / json_object / json_array / json_quote / json_patch
    // ========================================================================

    /**
     * @dataProvider provideJsonConstructorFunctions
     */
    public function testJsonConstructorFunctions(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonConstructorFunctions(): iterable
    {
        // json(): валидация/минификация JSON-текста
        yield 'json literal' => ["SELECT json('{\"a\": 1}')"];
        yield 'json array literal' => ["SELECT json('[1, 2, 3]')"];
        yield 'json column' => ['SELECT json(data) FROM t'];
        yield 'json qualified column' => ['SELECT json(t.data) FROM t'];
        yield 'json alias' => ['SELECT json(col) AS j FROM t'];
        yield 'json empty object' => ["SELECT json('{}')"];
        yield 'json empty array' => ["SELECT json('[]')"];
        yield 'json scalar literal' => ["SELECT json('42')"];

        // json_object(): пары ключ-значение
        yield 'json_object one pair' => ["SELECT json_object('a', 1)"];
        yield 'json_object two pairs' => ["SELECT json_object('a', 1, 'b', 2)"];
        yield 'json_object three pairs' => ["SELECT json_object('a', 1, 'b', 2, 'c', 3)"];
        yield 'json_object string values' => ["SELECT json_object('name', 'John', 'age', 30)"];
        yield 'json_object empty' => ['SELECT json_object()'];
        yield 'json_object column value' => ["SELECT json_object('a', col) FROM t"];
        yield 'json_object nested object' => ["SELECT json_object('a', json_object('b', 1))"];
        yield 'json_object nested array' => ["SELECT json_object('nested', json_array(1, 2, 3))"];
        yield 'json_object alias' => ["SELECT json_object('a', 1) AS obj"];

        // json_array(): список значений
        yield 'json_array ints' => ['SELECT json_array(1, 2, 3)'];
        yield 'json_array single' => ['SELECT json_array(1)'];
        yield 'json_array empty' => ['SELECT json_array()'];
        yield 'json_array strings' => ["SELECT json_array('a', 'b', 'c')"];
        yield 'json_array mixed' => ["SELECT json_array(1, 'a', 2.5, null)"];
        yield 'json_array column' => ['SELECT json_array(col) FROM t'];
        yield 'json_array nested arrays' => ['SELECT json_array(json_array(1), json_array(2))'];
        yield 'json_array nested object' => ["SELECT json_array(json_object('a', 1))"];
        yield 'json_array alias' => ['SELECT json_array(1, 2) AS arr'];

        // json_quote(): превращает SQL-значение в JSON-литерал
        yield 'json_quote string' => ["SELECT json_quote('hello')"];
        yield 'json_quote int' => ['SELECT json_quote(42)'];
        yield 'json_quote float' => ['SELECT json_quote(3.14)'];
        yield 'json_quote column' => ['SELECT json_quote(col) FROM t'];
        yield 'json_quote null' => ['SELECT json_quote(null)'];
        yield 'json_quote true' => ['SELECT json_quote(true)'];
        yield 'json_quote false' => ['SELECT json_quote(false)'];

        // json_patch(): слияние двух JSON-документов
        yield 'json_patch columns' => ['SELECT json_patch(a, b) FROM t'];
        yield 'json_patch literals' => ["SELECT json_patch('{\"a\": 1}', '{\"b\": 2}')"];
        yield 'json_patch objects' => ["SELECT json_patch(json_object('a', 1), json_object('b', 2))"];
        yield 'json_patch nested' => [
            "SELECT json_patch(json_object('a', json_object('x', 1)), '{\"a\": {\"y\": 2}}')",
        ];
    }

    // ========================================================================
    // 2. Извлечение и проверки: json_extract / json_type / json_valid / json_array_length
    // ========================================================================

    /**
     * @dataProvider provideJsonExtraction
     */
    public function testJsonExtraction(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonExtraction(): iterable
    {
        // json_extract: простые пути
        yield 'extract root' => ["SELECT json_extract(data, '$') FROM t"];
        yield 'extract member' => ["SELECT json_extract(data, '$.name') FROM t"];
        yield 'extract nested member' => ["SELECT json_extract(data, '$.a.b') FROM t"];
        yield 'extract deep nested' => ["SELECT json_extract(data, '$.a.b.c') FROM t"];
        yield 'extract array index' => ["SELECT json_extract(data, '$[0]') FROM t"];
        yield 'extract array then member' => ["SELECT json_extract(data, '$[0].name') FROM t"];
        yield 'extract member array' => ["SELECT json_extract(data, '$.items[0]') FROM t"];
        yield 'extract member array member' => ["SELECT json_extract(data, '$.items[0].id') FROM t"];
        yield 'extract chained indexes' => ["SELECT json_extract(data, '$.a[0][1][2]') FROM t"];
        yield 'extract from literal' => ["SELECT json_extract('{\"x\": 42}', '$.x')"];
        yield 'extract with alias' => ["SELECT json_extract(data, '$.a') AS a FROM t"];

        // json_extract: несколько путей сразу
        yield 'extract two paths' => ["SELECT json_extract(data, '$.a', '$.b') FROM t"];
        yield 'extract three paths' => ["SELECT json_extract(data, '$.a', '$.b', '$.c') FROM t"];

        // json_type
        yield 'json_type whole' => ['SELECT json_type(data) FROM t'];
        yield 'json_type with path' => ["SELECT json_type(data, '$.a') FROM t"];
        yield 'json_type literal' => ["SELECT json_type('{}', '$.a')"];
        yield 'json_type nested' => ["SELECT json_type(json_extract(data, '$.a')) FROM t"];

        // json_valid
        yield 'json_valid column' => ['SELECT json_valid(data) FROM t'];
        yield 'json_valid literal' => ["SELECT json_valid('{}')"];
        yield 'json_valid with flags' => ['SELECT json_valid(data, 6) FROM t'];
        yield 'json_valid in where' => ['SELECT * FROM t WHERE json_valid(data)'];

        // json_array_length
        yield 'json_array_length whole' => ['SELECT json_array_length(arr) FROM t'];
        yield 'json_array_length with path' => ["SELECT json_array_length(data, '$.items') FROM t"];
        yield 'json_array_length in where' => ["SELECT * FROM t WHERE json_array_length(data, '$.items') > 0"];

        // json_error_position
        yield 'json_error_position' => ['SELECT json_error_position(data) FROM t'];
    }

    // ========================================================================
    // 3. Мутации: json_insert / json_replace / json_set / json_remove
    // ========================================================================

    /**
     * @dataProvider provideJsonMutation
     */
    public function testJsonMutation(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonMutation(): iterable
    {
        // json_insert
        yield 'json_insert one' => ["SELECT json_insert(data, '$.new', 1) FROM t"];
        yield 'json_insert two pairs' => ["SELECT json_insert(data, '$.a', 'x', '$.b', 'y') FROM t"];

        // json_replace
        yield 'json_replace one' => ["SELECT json_replace(data, '$.a', 1) FROM t"];
        yield 'json_replace two pairs' => ["SELECT json_replace(data, '$.a', 'x', '$.b', 'y') FROM t"];

        // json_set
        yield 'json_set one' => ["SELECT json_set(data, '$.a', 1) FROM t"];
        yield 'json_set two pairs' => ["SELECT json_set(data, '$.a', 'x', '$.b', 2) FROM t"];
        yield 'json_set nested value' => ["SELECT json_set(data, '$.a', json_object('b', 1)) FROM t"];

        // json_remove
        yield 'json_remove one' => ["SELECT json_remove(data, '$.a') FROM t"];
        yield 'json_remove two' => ["SELECT json_remove(data, '$.a', '$.b') FROM t"];
        yield 'json_remove nested' => ["SELECT json_remove(data, '$.a.b.c') FROM t"];
    }

    // ========================================================================
    // 4. Агрегаты: json_group_array / json_group_object
    // ========================================================================

    /**
     * @dataProvider provideJsonAggregates
     */
    public function testJsonAggregates(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonAggregates(): iterable
    {
        yield 'json_group_array simple' => ['SELECT json_group_array(id) FROM t'];
        yield 'json_group_array grouped' => ['SELECT json_group_array(name) FROM t GROUP BY g'];
        yield 'json_group_array where' => ['SELECT json_group_array(x) FROM t WHERE a > 0'];
        yield 'json_group_array alias' => ['SELECT json_group_array(id) AS ids FROM t'];

        yield 'json_group_object columns' => ['SELECT json_group_object(k, v) FROM t'];
        yield 'json_group_object literal key' => ["SELECT json_group_object('id', id) FROM t"];
        yield 'json_group_object grouped' => ['SELECT json_group_object(k, v) FROM t GROUP BY g'];
        yield 'json_group_object alias' => ['SELECT json_group_object(k, v) AS obj FROM t'];
    }

    // ========================================================================
    // 5. JSON в SELECT-списке
    // ========================================================================

    /**
     * @dataProvider provideJsonInSelectList
     */
    public function testJsonInSelectList(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonInSelectList(): iterable
    {
        yield 'single extract' => ["SELECT json_extract(data, '$.a') AS a FROM t"];
        yield 'two extracts' => [
            "SELECT json_extract(data, '$.a') AS a, json_extract(data, '$.b') AS b FROM t",
        ];
        yield 'mixed columns' => ["SELECT id, json_extract(data, '$.name') AS name FROM t"];
        yield 'distinct extract' => ["SELECT DISTINCT json_extract(data, '$.type') FROM t"];
        yield 'object from columns' => ["SELECT json_object('id', id, 'name', name) FROM t"];
        yield 'array from columns' => ['SELECT json_array(id, name) FROM t'];
        yield 'extract and aggregate' => [
            "SELECT json_extract(data, '$.type') AS t, COUNT(*) AS c FROM t GROUP BY json_extract(data, '$.type')",
        ];
    }

    // ========================================================================
    // 6. JSON в WHERE
    // ========================================================================

    /**
     * @dataProvider provideJsonInWhere
     */
    public function testJsonInWhere(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonInWhere(): iterable
    {
        yield 'valid' => ['SELECT * FROM t WHERE json_valid(data)'];
        yield 'not valid' => ['SELECT * FROM t WHERE NOT json_valid(data)'];
        yield 'extract eq int' => ["SELECT * FROM t WHERE json_extract(data, '$.a') = 1"];
        yield 'extract gt' => ["SELECT * FROM t WHERE json_extract(data, '$.a') > 0"];
        yield 'extract eq string' => ["SELECT * FROM t WHERE json_extract(data, '$.name') = 'John'"];
        yield 'type check' => ["SELECT * FROM t WHERE json_type(data, '$.a') = 'integer'"];
        yield 'array length gt' => ["SELECT * FROM t WHERE json_array_length(data, '$.items') > 0"];
        yield 'extract not null' => ["SELECT * FROM t WHERE json_extract(data, '$.a') IS NOT NULL"];
        yield 'extract is null' => ["SELECT * FROM t WHERE json_extract(data, '$.a') IS NULL"];
        yield 'extract in list' => ["SELECT * FROM t WHERE json_extract(data, '$.a') IN (1, 2, 3)"];
        yield 'extract between' => ["SELECT * FROM t WHERE json_extract(data, '$.a') BETWEEN 0 AND 100"];
        yield 'extract like' => ["SELECT * FROM t WHERE json_extract(data, '$.name') LIKE 'A%'"];
        yield 'extract not like' => ["SELECT * FROM t WHERE json_extract(data, '$.name') NOT LIKE '%bot%'"];
        yield 'combined and' => [
            "SELECT * FROM t WHERE json_valid(data) AND json_extract(data, '$.a') > 0",
        ];
        yield 'combined or' => [
            "SELECT * FROM t WHERE json_extract(data, '$.a') = 1 OR json_extract(data, '$.b') = 2",
        ];
    }

    // ========================================================================
    // 7. JSON в ORDER BY / GROUP BY / HAVING
    // ========================================================================

    /**
     * @dataProvider provideJsonInOrderByGroupBy
     */
    public function testJsonInOrderByGroupBy(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonInOrderByGroupBy(): iterable
    {
        yield 'order by extract' => ["SELECT * FROM t ORDER BY json_extract(data, '$.a')"];
        yield 'order by extract desc' => ["SELECT * FROM t ORDER BY json_extract(data, '$.a') DESC"];
        yield 'order by two extracts' => [
            "SELECT * FROM t ORDER BY json_extract(data, '$.a'), json_extract(data, '$.b')",
        ];
        yield 'group by extract' => [
            "SELECT json_extract(data, '$.type'), COUNT(*) FROM t GROUP BY json_extract(data, '$.type')",
        ];
        yield 'group having' => [
            "SELECT json_extract(data, '$.type'), COUNT(*) FROM t GROUP BY json_extract(data, '$.type') HAVING COUNT(*) > 1",
        ];
    }

    // ========================================================================
    // 8. JSON в INSERT / UPDATE / DELETE ... RETURNING
    // ========================================================================

    /**
     * @dataProvider provideJsonInDml
     */
    public function testJsonInDml(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonInDml(): iterable
    {
        // INSERT
        yield 'insert json literal' => ["INSERT INTO t (data) VALUES ('{\"a\": 1}')"];
        yield 'insert json_object' => ["INSERT INTO t (data) VALUES (json_object('a', 1))"];
        yield 'insert json_array' => ['INSERT INTO t (data) VALUES (json_array(1, 2, 3))'];
        yield 'insert from select' => ["INSERT INTO t (data) SELECT json_object('id', id) FROM u"];
        yield 'insert returning extract' => [
            "INSERT INTO t (data) VALUES ('{}') RETURNING id, json_extract(data, '$.a')",
        ];

        // UPDATE
        yield 'update json_object' => ["UPDATE t SET data = json_object('a', 1) WHERE id = 1"];
        yield 'update json_set' => [
            "UPDATE t SET data = json_set(data, '$.a', 2) WHERE json_extract(data, '$.a') = 1",
        ];
        yield 'update json_remove' => ["UPDATE t SET data = json_remove(data, '$.a') WHERE id = 1"];
        yield 'update json_patch' => [
            "UPDATE t SET data = json_patch(data, '{\"a\": 2}') WHERE id = 1",
        ];
        yield 'update returning star' => ["UPDATE t SET data = json_object('a', 1) RETURNING *"];
        yield 'update returning extract' => [
            "UPDATE t SET data = json_object('a', 1) RETURNING id, json_extract(data, '$.a')",
        ];

        // DELETE
        yield 'delete where extract' => ["DELETE FROM t WHERE json_extract(data, '$.a') = 1"];
        yield 'delete where not valid' => ['DELETE FROM t WHERE json_valid(data) = 0'];
        yield 'delete where type' => ["DELETE FROM t WHERE json_type(data, '$.a') = 'null'"];
        yield 'delete returning extract' => [
            "DELETE FROM t WHERE json_valid(data) RETURNING id, json_extract(data, '$.a')",
        ];
    }

    // ========================================================================
    // 9. JSON в CTE и UNION
    // ========================================================================

    /**
     * @dataProvider provideJsonWithCte
     */
    public function testJsonWithCte(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonWithCte(): iterable
    {
        yield 'cte with extract' => [
            "WITH j AS (SELECT json_extract(data, '$.a') AS a FROM t) SELECT * FROM j",
        ];
        yield 'cte with object' => [
            "WITH j AS (SELECT json_object('id', id) AS obj FROM t) SELECT json_extract(obj, '$.id') FROM j",
        ];
        yield 'cte aggregate' => [
            "WITH j AS (SELECT json_group_array(id) AS ids FROM t) SELECT json_array_length(ids) FROM j",
        ];
    }

    /**
     * @dataProvider provideJsonWithUnion
     */
    public function testJsonWithUnion(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonWithUnion(): iterable
    {
        yield 'union extracts' => [
            "SELECT json_extract(a, '$.x') FROM t UNION SELECT json_extract(b, '$.x') FROM u",
        ];
        yield 'union all objects' => [
            "SELECT json_object('a', 1) UNION ALL SELECT json_object('b', 2)",
        ];
        yield 'union all with alias' => [
            "SELECT json_extract(data, '$.a') AS x FROM t UNION ALL SELECT json_extract(data, '$.b') AS x FROM u",
        ];
    }

    // ========================================================================
    // 10. Вложенные JSON-функции
    // ========================================================================

    /**
     * @dataProvider provideNestedJson
     */
    public function testNestedJson(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideNestedJson(): iterable
    {
        yield 'extract of object' => ["SELECT json_extract(json_object('a', 1), '$.a')"];
        yield 'extract of extract' => [
            "SELECT json_extract(json_extract(data, '$.a'), '$.b') FROM t",
        ];
        yield 'object of object' => ["SELECT json_object('outer', json_object('inner', 1))"];
        yield 'array of arrays' => ['SELECT json_array(json_array(1, 2), json_array(3, 4))'];
        yield 'array of object' => ["SELECT json_array(json_object('a', 1), json_object('b', 2))"];
        yield 'extract of array' => ["SELECT json_extract(json_array(1, 2, 3), '$[1]')"];
        yield 'type of extract' => ["SELECT json_type(json_extract(data, '$.a')) FROM t"];
        yield 'valid of extract' => ["SELECT json_valid(json_extract(data, '$.a')) FROM t"];
        yield 'patch of objects' => ["SELECT json_patch(json_object('a', 1), json_object('b', 2))"];
        yield 'set with extract' => [
            "SELECT json_set(data, '$.a', json_extract(other, '$.b')) FROM t",
        ];
        yield 'deeply nested' => [
            "SELECT json_extract(json_patch(json_object('a', json_array(1, 2, 3)), '{}'), '$.a[1]')",
        ];
    }

    // ========================================================================
    // 11. JSON в составе выражений
    // ========================================================================

    /**
     * @dataProvider provideJsonWithExpressions
     */
    public function testJsonWithExpressions(string $sql): void
    {
        $this->assertSingleStatement($sql);
    }

    public static function provideJsonWithExpressions(): iterable
    {
        yield 'add to extract' => ["SELECT json_extract(data, '$.a') + 1 FROM t"];
        yield 'multiply extract' => ["SELECT json_extract(data, '$.a') * 2 FROM t"];
        yield 'concat extracts' => [
            "SELECT json_extract(data, '$.a') || '-' || json_extract(data, '$.b') FROM t",
        ];
        yield 'cast extract int' => ["SELECT CAST(json_extract(data, '$.a') AS INTEGER) FROM t"];
        yield 'cast extract text' => ["SELECT CAST(json_extract(data, '$.name') AS TEXT) FROM t"];
        yield 'case over extract' => [
            "SELECT CASE WHEN json_extract(data, '$.a') > 0 THEN json_extract(data, '$.a') ELSE 0 END FROM t",
        ];
        yield 'is null check' => ["SELECT json_extract(data, '$.a') IS NULL FROM t"];
        yield 'compare extracts' => [
            "SELECT json_extract(data, '$.a') = json_extract(other, '$.a') FROM t",
        ];
        yield 'between extract' => ["SELECT json_extract(data, '$.a') BETWEEN 1 AND 10 FROM t"];
        yield 'in extract list' => ["SELECT json_extract(data, '$.a') IN (1, 2, 3) FROM t"];
    }

    // ========================================================================
    // 12. Проверки формы AST для ключевых JSON-конструкций
    // ========================================================================

    public function testJsonExtractParsesAsFunctionCall(): void
    {
        $script = $this->parser->parse("SELECT json_extract(data, '$.a') FROM t");
        self::assertCount(1, $script->statements);

        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);
        self::assertCount(1, $stmt->columns);

        $expr = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('json_extract', strtolower($expr->name));
        self::assertCount(2, $expr->arguments);

        self::assertInstanceOf(ColumnReference::class, $expr->arguments[0]);
        self::assertSame('data', $expr->arguments[0]->name);

        self::assertInstanceOf(Literal::class, $expr->arguments[1]);
        self::assertSame('$.a', $expr->arguments[1]->value);
    }

    public function testJsonObjectParsesAsFunctionCall(): void
    {
        $script = $this->parser->parse("SELECT json_object('a', 1, 'b', 2)");
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        $expr = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('json_object', strtolower($expr->name));
        self::assertCount(4, $expr->arguments);

        self::assertInstanceOf(Literal::class, $expr->arguments[0]);
        self::assertSame('a', $expr->arguments[0]->value);
        self::assertInstanceOf(Literal::class, $expr->arguments[1]);
        self::assertSame(1, $expr->arguments[1]->value);
        self::assertInstanceOf(Literal::class, $expr->arguments[2]);
        self::assertSame('b', $expr->arguments[2]->value);
        self::assertInstanceOf(Literal::class, $expr->arguments[3]);
        self::assertSame(2, $expr->arguments[3]->value);
    }

    public function testJsonArrayWithNumericArgs(): void
    {
        $script = $this->parser->parse('SELECT json_array(1, 2, 3)');
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        $expr = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('json_array', strtolower($expr->name));
        self::assertCount(3, $expr->arguments);

        foreach ($expr->arguments as $i => $arg) {
            self::assertInstanceOf(Literal::class, $arg);
            self::assertSame($i + 1, $arg->value);
        }
    }

    public function testJsonAggregateParsesAsFunctionCall(): void
    {
        $script = $this->parser->parse('SELECT json_group_array(id) FROM t GROUP BY g');
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        $expr = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertSame('json_group_array', strtolower($expr->name));
        self::assertCount(1, $expr->arguments);
    }

    public function testJsonInWhereAst(): void
    {
        $script = $this->parser->parse("SELECT id FROM t WHERE json_extract(data, '$.a') > 0");
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);
        self::assertNotNull($stmt->where);

        $where = $stmt->where;
        self::assertInstanceOf(BinaryOperation::class, $where);
        self::assertSame('>', $where->operator);

        self::assertInstanceOf(FunctionCall::class, $where->left);
        self::assertSame('json_extract', strtolower($where->left->name));

        self::assertInstanceOf(Literal::class, $where->right);
        self::assertSame(0, $where->right->value);
    }

    public function testJsonNestedObjectAst(): void
    {
        $script = $this->parser->parse("SELECT json_object('a', json_object('b', 1))");
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        $outer = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $outer);
        self::assertSame('json_object', strtolower($outer->name));
        self::assertCount(2, $outer->arguments);

        $inner = $outer->arguments[1];
        self::assertInstanceOf(FunctionCall::class, $inner);
        self::assertSame('json_object', strtolower($inner->name));
        self::assertCount(2, $inner->arguments);
    }

    public function testJsonExtractWithMultiplePathsAst(): void
    {
        $script = $this->parser->parse("SELECT json_extract(data, '$.a', '$.b') FROM t");
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);

        $expr = $stmt->columns[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $expr);
        self::assertCount(3, $expr->arguments);

        self::assertInstanceOf(ColumnReference::class, $expr->arguments[0]);
        self::assertInstanceOf(Literal::class, $expr->arguments[1]);
        self::assertSame('$.a', $expr->arguments[1]->value);
        self::assertInstanceOf(Literal::class, $expr->arguments[2]);
        self::assertSame('$.b', $expr->arguments[2]->value);
    }

    public function testJsonExtractAliasedAst(): void
    {
        $script = $this->parser->parse("SELECT json_extract(data, '$.a') AS a FROM t");
        $stmt = $script->statements[0];
        self::assertInstanceOf(SelectStatement::class, $stmt);
        self::assertCount(1, $stmt->columns);
        self::assertSame('a', $stmt->columns[0]->alias);
        self::assertInstanceOf(FunctionCall::class, $stmt->columns[0]->expression);
    }
}