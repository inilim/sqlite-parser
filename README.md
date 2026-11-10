# inilim/sqlite-parser

A typed SQLite SQL parser built on top of [`phplrt`](https://github.com/phplrt/phplrt).

The package accepts SQL script text and returns a typed AST — a hierarchy of
`Inilim\SqliteParser\Ast\*` objects instead of a single generic parse tree. This
makes it convenient to inspect, transform, traverse, serialize, and print SQL
statements programmatically.

Target SQLite dialect: **SQLite 3.54.\***.

## Requirements

- PHP 8.1 or newer (the AST uses `readonly` properties and modern type syntax).
- Composer.

## Installation

```bash
composer require inilim/sqlite-parser
```

## Quick start

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Ast\Script;

$parser = new Parser();

/** @var Script $script */
$script = $parser->parse('SELECT id, name FROM users WHERE active = 1;');

foreach ($script->statements as $statement) {
    // $statement is one of:
    // SelectStatement, InsertStatement, CreateTableStatement, ...
}
```

The root node is `Inilim\SqliteParser\Ast\Script`. It contains a flat list of
statements in source order:

```php
$script->statements; // list<Inilim\SqliteParser\Ast\Statement>
```

A single statement is available as:

```php
$statement = $script->statements[0];
```

## AST overview

The AST is intentionally typed. Common node groups are:

- Root:
  - `Ast\Script`
- Statements:
  - `Ast\Statement\SelectStatement`
  - `Ast\Statement\CompoundSelect`
  - `Ast\Statement\InsertStatement`
  - `Ast\Statement\UpdateStatement`
  - `Ast\Statement\DeleteStatement`
  - `Ast\Statement\CreateTableStatement`
  - `Ast\Statement\CreateIndexStatement`
  - `Ast\Statement\CreateViewStatement`
  - `Ast\Statement\CreateTriggerStatement`
  - `Ast\Statement\AlterTableStatement`
  - `Ast\Statement\DropStatement`
  - `Ast\Statement\PragmaStatement`
  - `Ast\Statement\TransactionStatement`
  - and others.
- Expressions:
  - `Ast\Expression\Literal`
  - `Ast\Expression\ColumnReference`
  - `Ast\Expression\BinaryOperation`
  - `Ast\Expression\UnaryOperation`
  - `Ast\Expression\FunctionCall`
  - `Ast\Expression\CaseExpression`
  - `Ast\Expression\CastExpression`
  - `Ast\Expression\Subquery`
  - `Ast\Expression\BetweenExpression`
  - `Ast\Expression\InExpression`
  - `Ast\Expression\IsNullExpression`
  - `Ast\Expression\LikeExpression`
  - `Ast\Expression\WindowFunction`
  - and others.

All AST nodes are regular PHP objects with public readonly properties. Most
nodes expose their child nodes through those properties, for example:

```php
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;

/** @var SelectStatement $select */
$select = $script->statements[0];

foreach ($select->columns as $resultColumn) {
    $expression = $resultColumn->expression;

    if ($expression instanceof ColumnReference) {
        echo $expression->name, PHP_EOL;
    }
}
```

## JSON serialization

All AST nodes implement `JsonSerializable`. This is useful for tests, debugging,
snapshots, and comparing parsed trees:

```php
$json = json_encode($script, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

`Script` serializes its `statements` array. Other nodes serialize their public
initialized properties.

## Low-level parser API

The convenience `Parser\Parser` wrapper parses strings directly. If you need
full control over the source, use the generated low-level
`Inilim\SqliteParser\Parser\SqliteParser` with any
`Phplrt\Contracts\Source\ReadableInterface` implementation.

```php
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\FileSource;

$parser = new SqliteParser();
$script = $parser->parse(FileSource::createFromPath(__DIR__ . '/schema.sql'));
```

You can also inject a preconfigured low-level parser into the string wrapper:

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Parser\SqliteParser;

$parser = new Parser(new SqliteParser());
$script = $parser->parse('SELECT 1;');
```

Parsing may throw:

- `Phplrt\Contracts\Lexer\Exception\LexerExceptionInterface`
- `Phplrt\Contracts\Parser\Exception\ParserExceptionInterface`

## Printing SQL back from AST

Use `Inilim\SqliteParser\Printer\SqlPrinter` to render an AST node back to SQL:

```php
use Inilim\SqliteParser\Printer\SqlPrinter;

$printer = new SqlPrinter();
$sql = $printer->print($script->statements[0]);

echo $sql;
```

The printer is intended for reconstruction and debugging. It does not preserve
the exact original whitespace, comments, or formatting of the input SQL.

## Dumping AST structure

For human-readable debugging, use `Inilim\SqliteParser\Printer\NodeDumper`:

```php
use Inilim\SqliteParser\Printer\NodeDumper;

$dumper = new NodeDumper();
echo $dumper->dump($script->statements[0]);
```

Example output shape:

```text
SelectStatement [distinct=false]
  columns: ResultColumnList
    items[0]: ResultColumn [alias=null]
      expression: ColumnReference [name="id", table=null]
  from: TableReference [name="users", alias=null]
```

## Column resolution

`Inilim\SqliteParser\Resolver\ColumnResolver` determines output column names
for SQL statements. It can resolve:

- `SELECT` result columns, including aliases;
- `SELECT *` expansion using a schema provider;
- columns from CTEs and derived tables;
- output names of compound selects (`UNION`, `INTERSECT`, `EXCEPT`);
- `RETURNING` columns for `INSERT`, `UPDATE`, and `DELETE`;
- columns produced by `CREATE VIEW ... AS SELECT`;
- columns produced by `CREATE TABLE ... AS SELECT`;
- nested `EXPLAIN` and `EXPLAIN QUERY PLAN` statements.

The resolver needs a schema provider to expand `*`. The package includes
`ArraySchema`:

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Resolver\ArraySchema;
use Inilim\SqliteParser\Resolver\ColumnResolver;

$parser = new Parser();

$schemaScript = $parser->parse(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT,
    email TEXT
);
SQL);

$schema = ArraySchema::fromScript($schemaScript);
$resolver = new ColumnResolver($schema);

$select = $parser->parse('SELECT * FROM users')->statements[0];
$columns = $resolver->resolve($select);

// ['id', 'name', 'email']
```

You can also create a schema manually:

```php
use Inilim\SqliteParser\Resolver\ArraySchema;

$schema = new ArraySchema([
    'users' => ['id', 'name', 'email'],
    'posts' => ['id', 'user_id', 'title', 'body'],
]);
```

Custom schema providers can implement
`Inilim\SqliteParser\Resolver\SchemaProvider`:

```php
use Inilim\SqliteParser\Resolver\SchemaProvider;

final class MySchema implements SchemaProvider
{
    public function getTableColumns(string $tableName): ?array
    {
        return match ($tableName) {
            'users' => ['id', 'name', 'email'],
            default => null,
        };
    }
}
```

### Column resolver limitations

The resolver is best-effort and intentionally documented:

- `SELECT t.*` and `RETURNING t.*` are treated as `*`, because the current AST
  does not store a qualifier on `Ast\Expression\Star`.
- Recursive CTEs are not expanded. A recursive CTE that references itself inside
  its own body may produce an empty expansion for `*`.
- If a table is unknown to the schema provider, `SELECT * FROM unknown_table`
  returns an empty list for that table.
- `WITH ... INSERT/UPDATE/DELETE` is not produced by the parser, because the DML
  statement nodes do not have a `with` property. `WITH ... SELECT` is supported.

## Traversal and transformation

The package provides several AST utility classes under
`Inilim\SqliteParser\Traversal`.

### Finding nodes

Use `NodeFinder` to find nodes by class or predicate:

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Traversal\NodeFinder;

$finder = new NodeFinder(ColumnReference::class);
$columnReferences = $finder->findAll($script->statements[0]);

$firstIdReference = (new NodeFinder(
    static fn ($node): bool => $node instanceof ColumnReference && $node->name === 'id'
))->findFirst($script->statements[0]);
```

### Visiting nodes

Use `NodeTraverser` and `NodeVisitor` for pre-order and post-order visitors:

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Traversal\NodeTraverser;
use Inilim\SqliteParser\Traversal\NodeVisitor;

final class ColumnCollector extends NodeVisitor
{
    /** @var list<ColumnReference> */
    public array $columns = [];

    public function enterNode(Node $node): void
    {
        if ($node instanceof ColumnReference) {
            $this->columns[] = $node;
        }
    }
}

$collector = new ColumnCollector();
$traverser = new NodeTraverser();
$traverser->addVisitor($collector);
$traverser->traverse($script->statements[0]);

$columnReferences = $collector->columns;
```

### Transforming nodes

Use `NodeTransformer` to replace or remove nodes. The callback returns:

- the same node instance to keep it unchanged;
- a new node instance to replace it;
- `null` to remove it, if the parent property is nullable or the value is inside
  an array. Removing a required non-nullable property throws `LogicException`.

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Traversal\NodeCloner;
use Inilim\SqliteParser\Traversal\NodeTransformer;

$transformer = new NodeTransformer(
    static fn (Node $node): ?Node
        => $node instanceof ColumnReference && $node->name === 'old'
            ? NodeCloner::with($node, ['name' => 'new'])
            : $node
);

$newStatement = $transformer->transform($script->statements[0]);
```

### Extracting children

Use `NodeChildExtractor` to get direct child nodes from any AST node:

```php
use Inilim\SqliteParser\Traversal\NodeChildExtractor;

$children = NodeChildExtractor::children($script->statements[0]);
```

## Supported SQL

Target dialect: SQLite **3.54.\***.

Implemented areas:

### SELECT

- Expressions, literals, column references, qualified column references.
- Functions and aggregate functions.
- `CAST`, `CASE` (searched and simple form).
- Comparison, arithmetic, concatenation, and logical operators.
- `BETWEEN`, `IN`, `IS NULL`, `IS NOT NULL`, `LIKE`, `GLOB`.
- Subqueries.
- `JOIN` forms, including `NATURAL`, `INNER`, `LEFT`, `RIGHT`, `FULL`,
  `CROSS`, `ON`, and `USING`.
- `WHERE`, `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT`, `OFFSET`.
- `DISTINCT` and `ALL`.
- Compound selects: `UNION`, `UNION ALL`, `INTERSECT`, `EXCEPT`.
- CTEs: `WITH [RECURSIVE]`.
- Window functions: `OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE ...)`
  and named windows.

### DML

- `INSERT`, including:
  - `OR REPLACE`, `OR IGNORE`, `OR ABORT`, `OR FAIL`, `OR ROLLBACK`;
  - `VALUES (...), (...), ...`;
  - `INSERT ... SELECT ...`;
  - `DEFAULT VALUES`;
  - `RETURNING`.
- `UPDATE`, including `OR ...` actions and `RETURNING`.
- `DELETE`, including `RETURNING`.
- `UPSERT`: `ON CONFLICT (...) DO UPDATE SET ...` and `DO NOTHING`.

### DDL

- `CREATE TABLE`, including:
  - `TEMP` / `TEMPORARY`;
  - `IF NOT EXISTS`;
  - column definitions;
  - `PRIMARY KEY`, `AUTOINCREMENT`, `NOT NULL`, `UNIQUE`, `CHECK`, `DEFAULT`,
    `COLLATE`, `REFERENCES`;
  - table constraints;
  - `WITHOUT ROWID` and `STRICT`;
  - `CREATE TABLE ... AS SELECT ...`.
- `CREATE INDEX`, including `UNIQUE`, `IF NOT EXISTS`, expression indexes, and
  partial indexes with `WHERE`.
- `CREATE VIEW`, including optional column list.
- `CREATE TRIGGER`, including timing, event, `UPDATE OF`, `FOR EACH ROW`,
  `WHEN`, and trigger body statements.
- `ALTER TABLE`:
  - `RENAME TO`;
  - `RENAME [COLUMN] old TO new`;
  - `ADD [COLUMN] column_definition`;
  - `DROP [COLUMN] column_name`.
- `DROP TABLE|INDEX|VIEW|TRIGGER [IF EXISTS] name`.

### Utility statements

- `PRAGMA`, including:
  - `PRAGMA name`;
  - `PRAGMA schema.name`;
  - `PRAGMA name = value`;
  - `PRAGMA schema.name = value`;
  - `PRAGMA name(value)`;
  - `PRAGMA schema.name(value)`.
- `ATTACH [DATABASE] expr AS schema_name`.
- `DETACH [DATABASE] schema_name`.
- Transactions:
  - `BEGIN [DEFERRED|IMMEDIATE|EXCLUSIVE] [TRANSACTION [name]]`;
  - `COMMIT [TRANSACTION [name]]`;
  - `END TRANSACTION [name]`;
  - `ROLLBACK [TRANSACTION]`;
  - `ROLLBACK TO [SAVEPOINT] name`.
- `SAVEPOINT name` and `RELEASE [SAVEPOINT] name`.
- `EXPLAIN statement` and `EXPLAIN QUERY PLAN statement`.

### JSON1 support

SQLite JSON functions and operators are parsed as ordinary function calls and
binary operators, so the AST supports common JSON1 usage such as:

- `json`, `json_object`, `json_array`, `json_quote`, `json_patch`;
- `json_extract`, `json_type`, `json_valid`, `json_array_length`,
  `json_error_position`;
- `json_insert`, `json_replace`, `json_set`, `json_remove`;
- `json_group_array`, `json_group_object`;
- `json_each` and `json_tree` as table-valued functions in `FROM`;
- JSON operators `->` and `->>`.

## Known limitations

- `END;` without `TRANSACTION` is not supported. It is ambiguous with
  `BEGIN ... END` trigger bodies and `CASE ... END`. Use `COMMIT` or
  `END TRANSACTION [name]` to close a transaction.
- Functions with keyword-like names such as `REPLACE`, `LEFT`, and `RIGHT` are
  not supported in `FunctionCall`.
- Parameterized types in `CAST` such as `VARCHAR(10)` are not supported — only a
  single type word is supported.
- The parser is syntactic. It does not perform full semantic validation, name
  resolution, type checking, or database schema validation.
- `SqlPrinter` reconstructs SQL but does not preserve comments or original
  formatting.
- Column resolver limitations are described in the “Column resolver limitations”
  section above.

## Development

Install dependencies:

```bash
composer install
```

Useful Composer scripts:

```bash
composer grammar:check   # validate the grammar
composer grammar:build   # regenerate src/Parser/SqliteParser.php
composer grammar:diff    # build and check that generated parser is not drifting
composer tests           # run PHPUnit
composer stan            # run PHPStan (level 8)
composer ci              # full pipeline: check + diff + tests + stan
```

The grammar-related utilities live in `src/Grammar/Reducers.php`. The generated
parser is `src/Parser/SqliteParser.php`; regenerate it with
`composer grammar:build` after grammar changes.

## Project structure

```text
src/
  Ast/
    Expression/          Typed expression nodes
    Statement/           Typed statement and clause nodes
    Node.php             Base AST node
    Script.php           Root AST node
    Statement.php        Base statement node
    Expression.php       Base expression node
  Grammar/
    Reducers.php         Reducer utilities for grammar actions
  Parser/
    Parser.php           Convenience string-based parser wrapper
    SqliteParser.php     Generated low-level phplrt parser
  Printer/
    NodeDumper.php       Human-readable AST dumper
    SqlPrinter.php       AST-to-SQL printer
  Resolver/
    ArraySchema.php      Array-backed schema provider
    ColumnResolver.php   Output column name resolver
    SchemaProvider.php   Schema provider interface
  Traversal/
    NodeChildExtractor.php
    NodeCloner.php
    NodeFinder.php
    NodeTransformer.php
    NodeTraverser.php
    NodeVisitor.php
tests/
  Grammar/               Parser and grammar tests
```

## Example: parse, inspect, resolve, print

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Printer\SqlPrinter;
use Inilim\SqliteParser\Resolver\ArraySchema;
use Inilim\SqliteParser\Resolver\ColumnResolver;
use Inilim\SqliteParser\Traversal\NodeFinder;

$parser = new Parser();

$schema = ArraySchema::fromScript($parser->parse(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT,
    email TEXT
);
SQL));

$script = $parser->parse('SELECT id, name FROM users WHERE email IS NOT NULL;');
$select = $script->statements[0];

$columns = (new ColumnResolver($schema))->resolve($select);
// ['id', 'name']

$refs = (new NodeFinder(ColumnReference::class))->findAll($select);

$sql = (new SqlPrinter())->print($select);
// SELECT id, name FROM users WHERE email IS NOT NULL
```