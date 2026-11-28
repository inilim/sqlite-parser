# LLM.md — inilim/sqlite-parser

Single-file, machine-oriented reference for LLM agents. Read this file instead of
loading the library sources. It describes **every public capability** of the
package: entry points, the complete typed AST, printer, dumper, column resolver,
traversal/transformation utilities, supported SQL, limitations, and dev workflow.

If this file disagrees with `README.md`, trust this file — it is derived from the
actual code (README contains a few outdated examples; see §10).

Notation used below:
- `list<T>` = PHP array with sequential integer keys (a PHP `array` at runtime).
- `array{a:int,b:?int}` = PHP array shape.
- All class names are relative to the root namespace `Inilim\SqliteParser\`
  unless fully qualified.
- "Ctor" lists constructor parameters in order, with defaults. Every parameter
  is a promoted **public readonly** property of the same name, unless stated
  otherwise.

---

## 1. Package facts

- Composer package: `inilim/sqlite-parser` (library, MIT).
- Purpose: parse SQLite SQL text into a **typed AST** (hierarchy of
  `Inilim\SqliteParser\Ast\*` objects), print AST back to SQL, dump AST for
  debugging, resolve output column names, traverse/transform AST.
- Target dialect: **SQLite 3.54.\***.
- PHP requirement (from `composer.json`, authoritative): `^8.4 | ^8.5 | ^8.6`
  (platform pinned to `8.4.99`). README's "PHP 8.1" claim is outdated.
- Runtime dependency: `phplrt/runtime ^4.2`. Dev: `phplrt/compiler`,
  `phpunit/phpunit ^9.6`, `phpstan/phpstan ^2.2` (level 8), `symfony/finder`,
  `inilim/tools`.
- Autoload: PSR-4 `Inilim\SqliteParser\` → `src/`; tests `Inilim\Test\` → `tests/`.
- Install: `composer require inilim/sqlite-parser`.
- The parser is **syntactic only**: no database connection, no semantic
  validation, no name resolution, no type checking, no schema validation.

## 2. Mental model

Pipeline: `SQL string → Parser → Ast\Script → list<Ast\Statement> → typed nodes`.

Class hierarchy:

```text
JsonSerializable
├── Ast\Script                      root container; NOT a Node; mutable
└── Ast\Node                        abstract base of every AST node
    ├── Ast\Statement (abstract)    all SQL statements (+ CompoundSelect)
    ├── Ast\Expression (abstract)   all SQL expressions
    └── clause/helper nodes         ResultColumn, JoinClause, WithClause, ...
```

Key rules:
- Every concrete AST node is `final`, with public readonly promoted properties.
  **Exceptions:** `Ast\Script` (mutable, not a Node) and
  `Ast\Statement\OrderBy` (mutable properties, see §4.4).
- `Node::jsonSerialize(): array<string,mixed>` returns all *initialized public*
  properties via reflection. `json_encode($node)` works on any node; nested
  nodes serialize recursively. `Node` has **no** `attributes` property (it was
  removed to fix exponential memory duplication). Old code using
  `$node->attributes` is broken — use `$node->jsonSerialize()`.
- `Script` *does* have a public `attributes` array (a constructor-time snapshot
  of `['statements' => ...]`); its `jsonSerialize()` returns that snapshot.
- String "enums" (node kinds, actions, operators) are plain strings with the
  exact spelling/case given in §4.
- Several helper nodes exist only as barriers against phplrt unwrap-flattening
  during grammar reduction (§4.4). In the final `SelectStatement`, lists are
  plain arrays and `where`/`having` are plain `Expression`s.

## 3. Entry points (parsing)

### 3.1 `Parser\Parser` — string wrapper (use this by default)

```php
final class Parser {
    public function __construct(?SqliteParser $parser = null);
    public function parse(string $sql): \Inilim\SqliteParser\Ast\Script;
}
```

```php
use Inilim\SqliteParser\Parser\Parser;

$parser = new Parser();
$script = $parser->parse('SELECT id, name FROM users WHERE active = 1;');
foreach ($script->statements as $statement) {
    // $statement is a typed Ast\Statement subclass
}
```

### 3.2 `Parser\SqliteParser` — generated low-level phplrt parser

Accepts any `Phplrt\Contracts\Source\ReadableInterface`. Returns the root
`Ast\Script`. **Generated file — never edit by hand** (see §11).

```php
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\FileSource;

$parser = new SqliteParser();
$script = $parser->parse(FileSource::createFromPath(__DIR__ . '/schema.sql'));
```

You can inject a preconfigured low-level parser into the wrapper:
`new Parser(new SqliteParser())`. String sources:
`Phplrt\Source\StringSource::createFromString($sql)`.

### 3.3 Exceptions

Parsing may throw:
- `Phplrt\Contracts\Lexer\Exception\LexerExceptionInterface`
- `Phplrt\Contracts\Parser\Exception\ParserExceptionInterface`

There is no "partial result" API: invalid SQL throws; unsupported constructs
(§10) are rejected at parse time.

### 3.4 `Ast\Script` — root container

```php
class Script implements JsonSerializable {           // NOT final, NOT a Node
    public array $statements;   // list<Ast\Statement>, source order, MUTABLE
    public array $attributes;   // array{statements: list<Statement>} snapshot
    public function __construct(array $statements = []);
    public function jsonSerialize(): array;          // returns $attributes
}
```

- Empty statements (bare `;`) become `Statement\EmptyStatement` instances.
- **Trap:** `Script` is not a `Node`. You cannot pass it to `SqlPrinter`,
  `NodeDumper`, `NodeFinder`, `NodeTraverser`, `NodeTransformer` — they all
  require `Ast\Node`. Iterate `$script->statements` instead.
- **Trap:** mutating `$script->statements` after construction does **not**
  update `$script->attributes`, so `json_encode($script)` shows the snapshot.

## 4. Complete AST inventory

Base classes (all in `Inilim\SqliteParser\Ast`):
- `Node` — abstract, implements `JsonSerializable`; empty constructor;
  `jsonSerialize(): array<string,mixed>`.
- `Statement extends Node` — abstract marker for SQL statements.
- `Expression extends Node` — abstract marker for SQL expressions.

### 4.1 Expressions — `Ast\Expression\*`

**`Literal`** (extends Expression)
- Ctor: `(mixed $value, string $kind)`.
- `$kind`: `'int' | 'float' | 'string' | 'null'`; `$value` is the corresponding
  PHP `int|float|string|null`. Strings are stored unescaped/unquoted.

**`ColumnReference`** (extends Expression)
- Ctor: `(string $name, ?string $table = null)`.
- `id` → name='id', table=null; `t.id` → name='id', table='t'.
  Quotes are stripped from identifiers during reduction.

**`Star`** (extends Expression)
- No constructor parameters, no properties (`jsonSerialize()` → `[]`).
- Represents `*` in `SELECT *` and `COUNT(*)`.
- **No qualifier is stored:** `t.*` is indistinguishable from `*`.

**`BinaryOperation`** (extends Expression)
- Ctor: `(string $operator, Expression $left, Expression $right)`.
- Operators: comparison `=`, `==`, `!=`, `<>`, `<`, `<=`, `>`, `>=`;
  arithmetic `+`, `-`, `*`, `/`, `%`; concatenation `||`;
  bitwise `|`, `&`, `<<`, `>>`; logical `AND`, `OR` (uppercase);
  JSON1 `->`, `->>`.
- `IS [NOT] NULL`, `LIKE`, `GLOB`, `BETWEEN`, `IN` are **separate nodes**
  (below), not BinaryOperation.

**`UnaryOperation`** (extends Expression)
- Ctor: `(string $operator, Expression $operand)`.
- Operators: `NOT`, `-`, `+`, `~`.

**`BetweenExpression`** (extends Expression)
- Ctor: `(Expression $expression, Expression $lower, Expression $upper, bool $not = false)`.
- `expr [NOT] BETWEEN lower AND upper`.

**`InExpression`** (extends Expression)
- Ctor: `(Expression $expression, array $list = [], bool $not = false, ?Statement $subquery = null)`.
- `$list`: `list<Expression>`. Value list and `$subquery` are mutually
  exclusive: `IN (a,b)` uses `$list`; `IN (SELECT ...)` uses `$subquery`
  (a `SelectStatement` or `CompoundSelect`) with `$list = []`.

**`IsNullExpression`** (extends Expression)
- Ctor: `(Expression $expression, bool $not = false)`.
- `expr IS NULL` / `expr IS NOT NULL`.

**`LikeExpression`** (extends Expression)
- Ctor: `(Expression $expression, Expression $pattern, bool $not = false, bool $glob = false)`.
- Covers `LIKE` and `GLOB`, with optional `NOT`. No ESCAPE clause support.

**`CastExpression`** (extends Expression)
- Ctor: `(Expression $expression, string $type)`.
- `$type` is a single uppercased word (`INTEGER`, `TEXT`, `REAL`, `BLOB`,
  `NUMERIC`, ...). Parameterized types like `VARCHAR(10)` are **not supported**.

**`CaseExpression`** (extends Expression)
- Ctor: `(?Expression $subject, array $whens, ?Expression $else)`.
- `$whens`: `list<WhenClause>`. Searched CASE → `$subject = null`;
  simple CASE (`CASE x WHEN ...`) → `$subject` set. `$else` nullable.

**`WhenClause`** (extends **Node**, not Expression)
- Ctor: `(Expression $when, Expression $then)`.

**`FunctionCall`** (extends Expression)
- Ctor: `(string $name, array $arguments = [], bool $distinct = false)`.
- `$arguments`: `list<Expression>`; `COUNT(*)` → `[new Star()]`;
  `COUNT(DISTINCT x)` → `distinct=true`. Name keeps the original SQL case.
- All SQLite/JSON1 functions are ordinary FunctionCalls (no special nodes).
- **Not supported:** functions with keyword-like names (`REPLACE`, `LEFT`,
  `RIGHT`).

**`WindowFunction`** (extends Expression)
- Ctor: `(FunctionCall $function, ?WindowSpecification $specification = null, ?string $windowName = null)`.
- `f(...) OVER (...)` → `$specification` set; `f(...) OVER w` →
  `$windowName` set. Exactly one of the two is used.

**`Subquery`** (extends Expression)
- Ctor: `(Statement $query)`.
- `(SELECT ...)` used as a scalar expression. `$query` is `SelectStatement`
  or `CompoundSelect`.

**`ExpressionList`** (extends **Node**, not Expression)
- Ctor: `(array $items)` — `list<Expression>`.
- Comma-separated expression list wrapper (barrier node; tuple-style lists).

### 4.2 Statements — `Ast\Statement\*` (all extend `Ast\Statement`)

**`SelectStatement`**
- Ctor: `(array $columns, bool $distinct = false, ?TableReference $from = null,
  array $joins = [], ?Expression $where = null, ?GroupByClause $groupBy = null,
  ?Expression $having = null, ?WithClause $with = null, ?array $orderBy = null,
  ?array $limit = null)`.
- `$columns`: `list<ResultColumn>`; `$joins`: `list<JoinClause>`;
  `$orderBy`: `list<OrderBy>|null`;
  `$limit`: `array{limit:int, offset:?int}|null` — **integers only**, not
  expressions.
- `distinct=true` for `SELECT DISTINCT`; `ALL` is not stored (default false).
- `where`/`having` are plain `Expression`s (no wrapper nodes in the final AST).
- `WITH` is attached here (or on `CompoundSelect`); DML nodes have no `with`.

**`CompoundSelect`**
- Ctor: `(array $selects, array $operators, ?WithClause $with = null,
  ?array $orderBy = null, ?array $limit = null)`.
- `$selects`: `list<SelectStatement>` (≥2); `$operators`: `list<string>` with
  values `UNION`, `UNION ALL`, `INTERSECT`, `EXCEPT`;
  invariant: `count($operators) === count($selects) - 1`.
- Trailing `ORDER BY`/`LIMIT` of the whole compound live here, not on the
  individual selects. Same `$orderBy`/`$limit` types as SelectStatement.

**`InsertStatement`**
- Ctor: `(string $table, ?array $columns = null, ?array $rows = null,
  ?Statement $select = null, bool $defaultValues = false,
  ?string $orAction = null, ?UpsertClause $upsert = null,
  ?ReturningClause $returning = null)`.
- `$columns`: `list<string>|null`; `$rows`: `list<list<Expression>>|null`.
- Body invariant: exactly one of `$rows` / `$select` / `$defaultValues=true`.
- `$orAction`: `REPLACE | IGNORE | ABORT | FAIL | ROLLBACK` (for `INSERT OR ...`).

**`UpdateStatement`**
- Ctor: `(string $table, array $assignments = [], ?Expression $where = null,
  ?string $orAction = null, ?ReturningClause $returning = null)`.
- `$assignments`: `list<Assignment>`; `$orAction` same values as INSERT.
- No FROM/JOIN support in UPDATE.

**`DeleteStatement`**
- Ctor: `(string $table, ?Expression $where = null, ?ReturningClause $returning = null)`.

**`CreateTableStatement`**
- Ctor: `(string $table, array $columns = [], array $tableConstraints = [],
  bool $ifNotExists = false, bool $temporary = false, bool $withoutRowid = false,
  bool $strict = false, ?Statement $asSelect = null)`.
- `$columns`: `list<ColumnDefinition>`; `$tableConstraints`: `list<TableConstraint>`.
- CTAS form: `$asSelect` set (SelectStatement/CompoundSelect), `$columns` empty.

**`CreateIndexStatement`**
- Ctor: `(string $name, string $table, array $columns = [], bool $unique = false,
  bool $ifNotExists = false, ?Expression $where = null)`.
- `$columns`: `list<string>` — indexed items are stored as **strings**, not
  Expression nodes (expression indexes reduce to string form).
- `$where` → partial index.

**`CreateViewStatement`**
- Ctor: `(string $name, Statement $select, bool $ifNotExists = false,
  bool $temporary = false, ?array $columns = null)`.
- `$columns`: `list<string>|null` — explicit column list after the view name.

**`CreateTriggerStatement`**
- Ctor: `(string $name, string $event, string $table, string $timing = 'BEFORE',
  array $updateOf = [], bool $forEachRow = false, ?Expression $when = null,
  array $statements = [], bool $ifNotExists = false, bool $temporary = false)`.
- `$event`: `INSERT | UPDATE | DELETE`; `$timing`: `BEFORE | AFTER | INSTEAD OF`.
- `$updateOf`: `list<string>` (for `UPDATE OF col, ...`);
  `$statements`: `list<Statement>` — trigger body statements.

**`AlterTableStatement`**
- Ctor: `(string $table, string $action, ?string $oldName = null,
  ?string $newName = null, ?ColumnDefinition $column = null)`.
- `$action` → used fields:
  - `RENAME_TO` → `$newName`
  - `RENAME_COLUMN` → `$oldName`, `$newName`
  - `ADD_COLUMN` → `$column`
  - `DROP_COLUMN` → `$oldName`

**`DropStatement`**
- Ctor: `(string $objectType, string $name, bool $ifExists = false)`.
- `$objectType`: `TABLE | INDEX | VIEW | TRIGGER`.

**`PragmaStatement`**
- Ctor: `(string $name, ?Expression $value = null, ?string $schema = null)`.
- Covers `PRAGMA name`, `PRAGMA schema.name`, `= value` and `(value)` forms
  (both value forms map to `$value`; usually a `Literal`).

**`AttachStatement`**
- Ctor: `(Expression $expression, string $schemaName)`.

**`DetachStatement`**
- Ctor: `(string $schemaName)`.

**`TransactionStatement`**
- Ctor: `(string $type, string $mode = '', ?string $name = null, bool $toSavepoint = false)`.
- `$type`: `BEGIN | COMMIT | END | ROLLBACK`.
- `$mode`: `'' | DEFERRED | IMMEDIATE | EXCLUSIVE` (BEGIN only).
- `ROLLBACK TO [SAVEPOINT] name` → type=`ROLLBACK`, `toSavepoint=true`,
  `$name` = savepoint name.
- `END` is only accepted as `END TRANSACTION [name]` (bare `END;` unsupported).

**`SavepointStatement`**
- Ctor: `(string $type, string $name)`; `$type`: `SAVEPOINT | RELEASE`.

**`ExplainStatement`**
- Ctor: `(Statement $statement, bool $queryPlan = false)`.
- Wraps any statement; `queryPlan=true` for `EXPLAIN QUERY PLAN`.

**`EmptyStatement`**
- No constructor parameters, no properties. Placeholder for a bare `;`.

### 4.3 Clause nodes referenced by statements — `Ast\Statement\*` (extend Node)

**`ResultColumn`**
- Ctor: `(Expression $expression, ?string $alias = null)`.
- One item of a SELECT list or RETURNING list.

**`ReturningClause`**
- Ctor: `(array $columns = [])` — `list<ResultColumn>`.

**`Assignment`**
- Ctor: `(string $column, ?Expression $value = null)`.
- One `SET col = expr` item (UPDATE and UPSERT).

**`TableReference`**
- Ctor: `(?string $name = null, ?string $alias = null, ?Statement $subquery = null,
  ?array $columnAliases = null)`.
- `$name` XOR `$subquery` is set. `$subquery` → derived table
  `(SELECT ...) AS t`. `$columnAliases`: `list<string>|null` for
  `(SELECT ...) AS t (a, b)`. Also used for table-valued functions
  (`json_each(...)`, `json_tree(...)`) in FROM.

**`JoinClause`**
- Ctor: `(string $type, TableReference $table, ?Expression $on = null,
  ?array $using = null, bool $natural = false)`.
- `$type`: `'' | INNER | LEFT | RIGHT | FULL | CROSS` (`''` = plain `JOIN`).
- `$using`: `list<string>|null`; `$on` XOR `$using` (both null for CROSS).

**`GroupByClause`**
- Ctor: `(array $expressions = [])` — `list<Expression>`.
- Stored as-is in `SelectStatement::$groupBy`.

**`WithClause`**
- Ctor: `(bool $recursive = false, array $ctes = [])` — `list<CommonTableExpression>`.

**`CommonTableExpression`**
- Ctor: `(string $name, ?array $columns, Statement $query)`.
- `$columns`: `list<string>|null` (note: parameter is required, but nullable).

**`OrderBy`** — **the only mutable AST node**
- Ctor: `(?Expression $expression = null, bool $desc = false)`.
- Properties are public **non-readonly**; does not call `parent::__construct()`.
- Extra methods: `getExpression(): ?Expression`, `getDesc(): bool`,
  `isDesc(): bool`, `isAsc(): bool`.
- `desc=false` covers both `ASC` and unspecified direction.

**`WindowSpecification`**
- Ctor: `(?array $partitionBy = null, ?array $orderBy = null, ?FrameSpec $frame = null)`.
- `$partitionBy`: `list<Expression>|null`; `$orderBy`: `list<OrderBy>|null`.

**`FrameSpec`**
- Ctor: `(string $unit, FrameBound $start, ?FrameBound $end = null)`.
- `$unit`: `ROWS | RANGE`. `$end !== null` → `BETWEEN start AND end`.

**`FrameBound`**
- Ctor: `(string $type, ?Expression $expression = null)`.
- `$type`: `UNBOUNDED_PRECEDING | UNBOUNDED_FOLLOWING | CURRENT_ROW |
  EXPR_PRECEDING | EXPR_FOLLOWING`. `$expression` only for `EXPR_*`.

**`UpsertClause`**
- Ctor: `(?array $conflictTarget = null, ?array $assignments = null,
  ?Expression $where = null)`.
- `$conflictTarget`: `list<string>|null`; `$assignments`: `list<Assignment>|null`.
- **`$assignments === null` means `DO NOTHING`**; otherwise `DO UPDATE SET ...`
  with optional `$where`.

**`ColumnDefinition`**
- Ctor: `(string $name, ?string $type = null, array $constraints = [])`.
- `$type`: type name as string or null (untyped column);
  `$constraints`: `list<ColumnConstraint>`.

**`ColumnConstraint`**
- Ctor: `(string $type, ?Expression $expression = null, ?string $value = null,
  ?ForeignKeyClause $foreignKey = null, ?string $conflictAction = null,
  bool $autoincrement = false, ?bool $asc = null)`.
- `$type`: `PRIMARY_KEY | NOT_NULL | UNIQUE | CHECK | DEFAULT | COLLATE | REFERENCES`.
- Field usage: CHECK/DEFAULT → `$expression`; DEFAULT literal / COLLATE name →
  `$value`; REFERENCES → `$foreignKey`; PRIMARY KEY direction → `$asc`
  (null = unspecified, true = ASC, false = DESC); `$autoincrement` for
  AUTOINCREMENT; `$conflictAction` = ON CONFLICT action
  (e.g. `ROLLBACK`, `ABORT`, `FAIL`, `IGNORE`, `REPLACE`).

**`TableConstraint`**
- Ctor: `(string $type, ?string $name = null, ?array $columns = null,
  ?Expression $expression = null, ?ForeignKeyClause $foreignKey = null,
  ?string $conflictAction = null)`.
- `$type`: `PRIMARY_KEY | UNIQUE | CHECK | FOREIGN_KEY`;
  `$name` = name after `CONSTRAINT`; `$columns`: `list<string>|null`;
  CHECK → `$expression`; FOREIGN KEY → `$foreignKey`.

**`ForeignKeyClause`**
- Ctor: `(string $table, ?array $columns = null, ?array $localColumns = null,
  ?string $onDelete = null, ?string $onUpdate = null)`.
- `$columns` / `$localColumns`: `list<string>|null`.
- Action strings: `CASCADE | RESTRICT | SET_NULL | SET_DEFAULT | NO_ACTION`.

**`ForeignKeyAction`**
- Ctor: `(string $type, string $action)`; `$type`: `DELETE | UPDATE`;
  `$action`: same action strings as above.

### 4.4 Internal / barrier helper nodes (extend Node)

These exist mainly to protect grammar reduction from phplrt unwrap-flattening.
You may encounter them when building AST manually; `SqlPrinter` can render them
standalone. In the final parsed `SelectStatement`/`InsertStatement` they are
already unpacked into plain arrays/fields.

- **`ResultColumnList`** — `(array $items)` — `list<ResultColumn>`.
- **`OrderByList`** — `(array $items)` — `list<OrderBy>`.
- **`AssignmentList`** — `(array $items)` — `list<Assignment>`.
- **`IdentListWrapper`** — `(array $items)` — `list<string>`.
- **`FromClause`** — `(TableReference $base, array $joins = [])` —
  `list<JoinClause>`. Prints without the `FROM` keyword.
- **`WhereClause`** — `(Expression $expression)`. Prints `WHERE expr`.
- **`HavingClause`** — `(Expression $expression)`. Prints `HAVING expr`.
- **`JoinConstraint`** — `(?Expression $on = null, ?array $using = null)`.
- **`InsertValuesBody`** — `(array $rows)` — `list<list<Expression>>`.
- **`InsertDefaultBody`** — no params; marker for `DEFAULT VALUES`.
- **`TableOptions`** — `(bool $withoutRowid = false, bool $strict = false)`.
- **`TriggerBody`** — `(array $statements)` — `list<Statement>`.
- **`TriggerEventInfo`** — `(string $event, array $updateOf = [])`.

### 4.5 SQL construct → node quick map

| SQL | Node |
|---|---|
| `SELECT ...` | `SelectStatement` |
| `... UNION [ALL] / INTERSECT / EXCEPT ...` | `CompoundSelect` |
| `WITH ...` | `WithClause` + `CommonTableExpression` |
| `INSERT / UPDATE / DELETE` | `InsertStatement` / `UpdateStatement` / `DeleteStatement` |
| `ON CONFLICT ... DO ...` | `UpsertClause` |
| `RETURNING ...` | `ReturningClause` |
| `CREATE TABLE / INDEX / VIEW / TRIGGER` | `CreateTableStatement` / `CreateIndexStatement` / `CreateViewStatement` / `CreateTriggerStatement` |
| `ALTER TABLE` / `DROP ...` | `AlterTableStatement` / `DropStatement` |
| `PRAGMA` / `ATTACH` / `DETACH` | `PragmaStatement` / `AttachStatement` / `DetachStatement` |
| `BEGIN / COMMIT / END TRANSACTION / ROLLBACK [TO]` | `TransactionStatement` |
| `SAVEPOINT / RELEASE` | `SavepointStatement` |
| `EXPLAIN [QUERY PLAN]` | `ExplainStatement` |
| literals / `NULL` | `Literal` |
| `col`, `t.col` | `ColumnReference` |
| `*` | `Star` |
| `a = b`, `a + b`, `a || b`, `a AND b`, `j -> '$.x'` | `BinaryOperation` |
| `NOT x`, `-x`, `+x`, `~x` | `UnaryOperation` |
| `BETWEEN` / `IN` / `IS [NOT] NULL` / `LIKE` / `GLOB` | `BetweenExpression` / `InExpression` / `IsNullExpression` / `LikeExpression` |
| `CAST(x AS T)` / `CASE ... END` | `CastExpression` / `CaseExpression` (+`WhenClause`) |
| `f(args)` / `f(...) OVER ...` | `FunctionCall` / `WindowFunction` |
| `(SELECT ...)` as expression | `Subquery` |
| `(SELECT ...) AS t` in FROM | `TableReference` with `$subquery` |
| `JOIN ...` | `JoinClause` |
| `ORDER BY` item | `OrderBy` |
| `OVER (PARTITION BY ... ORDER BY ... ROWS ...)` | `WindowSpecification` (+`FrameSpec`, `FrameBound`) |

## 5. Printing SQL — `Printer\SqlPrinter`

```php
final class SqlPrinter {
    public function print(Node $node): string;
    public function format(Node $node): string;   // alias of print()
}
```

- Accepts **any `Ast\Node`** listed in §4, including clause nodes
  (`ReturningClause` → `RETURNING ...`, `WhereClause` → `WHERE ...`,
  `WithClause` → `WITH ...`, `InsertDefaultBody` → `DEFAULT VALUES`, ...).
- **Does not accept `Ast\Script`** (not a Node). Print statements in a loop.
- `EmptyStatement` renders as `''`. Unknown node classes throw
  `\RuntimeException('Cannot render node: ' . $class)`.
- Output has **no trailing semicolon**; add it yourself when joining statements.
- Reconstruction only: original whitespace, comments, and formatting are lost.

Rendering rules worth knowing:
- Identifier quoting: unquoted if it matches `/^[\p{L}_]\w*$/u` **and** is not
  in the printer's reserved-word list (SELECT, FROM, ..., TEXT, INTEGER, ...);
  otherwise double-quoted with `"` doubled. Unicode letters are allowed
  unquoted.
- String literals: single quotes, `'` doubled. `NULL` for kind `'null'`.
  Floats via `var_export` (e.g. `1.0`).
- `DISTINCT` printed when true; `ALL` cannot be reconstructed (not stored).
- `LIMIT n [OFFSET m]` from the int array shape.
- `PRAGMA name(value)` is normalized to `PRAGMA name = value`.
- `ATTACH`/`DETACH` print without the optional `DATABASE` keyword;
  `ROLLBACK` prints as `ROLLBACK TRANSACTION`; `RELEASE` as
  `RELEASE SAVEPOINT name`; `END` as `END TRANSACTION [name]`.
- Trigger body prints as `BEGIN stmt; stmt; END`.
- Parenthesization is precedence-based but **only between BinaryOperation
  parent/child pairs**; other nested expression kinds are not auto-wrapped.
  Precedence (low→high): `OR`=1; `AND`=2; comparisons/`IS`/`LIKE`/`GLOB`/
  `BETWEEN`/`IN` (incl. NOT forms)=3; `|`=4; `&`=5; `<<`,`>>`=6; `+`,`-`=7;
  `*`,`/`,`%`=8; `||`=9; unknown operators (e.g. `->`, `->>`) default to 99.
  Right child of equal precedence gets parens unless the operator is
  associative (`+`, `*`, `||`, `AND`, `OR`, `&`, `|`, `<<`, `>>`).
  Unary operators wrap a BinaryOperation operand in parens.

```php
use Inilim\SqliteParser\Printer\SqlPrinter;

$printer = new SqlPrinter();
foreach ($script->statements as $stmt) {
    echo $printer->print($stmt), ";\n";
}
```

## 6. Debug dumping — `Printer\NodeDumper`

```php
final class NodeDumper {
    public function dump(Node $root): string;
}
```

- Human-readable indented tree. Scalar properties print inline after the short
  class name: `SelectStatement [distinct=false]`. Child nodes and arrays of
  nodes print on indented lines (`columns:` / `items[0]: ...`). Strings are
  quoted/escaped; arrays of scalars print as `[0:..., 1:...]`.
- Accepts `Ast\Node` only — **not** `Ast\Script`; dump statements individually.

```php
echo (new NodeDumper())->dump($script->statements[0]);
```

For machine-readable snapshots use `json_encode($node)` or
`json_encode($script, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)`.

## 7. Column resolution — `Resolver\*`

### 7.1 `Resolver\SchemaProvider` (interface)

```php
interface SchemaProvider {
    /** @return list<string>|null  null if the table is unknown */
    public function getTableColumns(string $tableName): ?array;
}
```

### 7.2 `Resolver\ArraySchema` (final, implements SchemaProvider; immutable)

```php
final class ArraySchema implements SchemaProvider {
    public function __construct(array $tables = []);                 // array<string, list<string>>
    public static function fromCreateStatements(array $statements): self; // list<CreateTableStatement>
    public static function fromScript(Script $script): self;         // extracts CREATE TABLE, ignores rest
    public function getTableColumns(string $tableName): ?array;
    public function addTable(string $tableName, array $columns): self;  // returns NEW instance
    public function toArray(): array;                                // array<string, list<string>>
    public function merge(ArraySchema $schema): self;                // returns NEW instance
    public static function mergeFrom(ArraySchema ...$schemas): self; // later schemas win
}
```

Notes: `fromScript()` only reads `CreateTableStatement`s; a CTAS table
(`CREATE TABLE t AS SELECT`) contributes an **empty** column list because its
AST `$columns` is empty. Views are not collected.

### 7.3 `Resolver\ColumnResolver` (final)

```php
final class ColumnResolver {
    public function __construct(SchemaProvider $schema, ?SqlPrinter $printer = null);
    /** @return list<string> */
    public function resolve(Statement $statement): array;
}
```

Behavior matrix:

| Input statement | Result |
|---|---|
| `SELECT a AS x, b` | `['x', 'b']` |
| `SELECT t.col` | `['col']` (qualifier dropped) |
| `SELECT COUNT(*), 1+2` | printed expression text, e.g. `['COUNT(*)', '1 + 2']` |
| `SELECT * FROM t` | schema columns of `t` |
| `SELECT * FROM t1 JOIN t2 ...` | t1 columns, then each JOIN table's columns, in FROM/JOIN order |
| `SELECT * FROM (SELECT ...) AS t` | inner SELECT's output columns (recursive) |
| `... AS t (a, b)` | explicit column aliases win |
| `WITH cte AS (...) SELECT * FROM cte` | CTE columns; chained CTEs see earlier CTEs; CTE shadows real table |
| `CompoundSelect` | names from the **first** SELECT |
| `INSERT/UPDATE/DELETE ... RETURNING ...` | RETURNING names; `RETURNING *` expands to **target table** columns |
| `CREATE VIEW v [(cols)] AS SELECT ...` | explicit `cols`, else inner SELECT columns |
| `CREATE TABLE t AS SELECT ...` | inner SELECT columns |
| `EXPLAIN [QUERY PLAN] stmt` | columns of the **inner statement** (not the real EXPLAIN output columns) |
| anything else (plain CREATE TABLE, CREATE INDEX, DROP, ALTER, PRAGMA, transactions, ATTACH/DETACH, ...) | `[]` |

Name derivation per result column: alias → `ColumnReference->name` →
`Star` expansion → `SqlPrinter->print($expression)` for any other expression.

Documented limitations (best-effort by design):
- `t.*` / `RETURNING t.*` treated as plain `*` (Star has no qualifier).
- Recursive CTEs are not expanded (self-reference yields `[]` for `*`).
- Unknown table in the provider → `[]` for that table's `*` expansion.
- `WITH ... INSERT/UPDATE/DELETE` is not produced by the parser at all
  (DML nodes have no `with` property); `WITH ... SELECT` works.

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Resolver\ArraySchema;
use Inilim\SqliteParser\Resolver\ColumnResolver;

$parser = new Parser();
$schema = ArraySchema::fromScript($parser->parse(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT);'
));
// or: new ArraySchema(['users' => ['id', 'name', 'email']]);
$resolver = new ColumnResolver($schema);
$select = $parser->parse('SELECT * FROM users')->statements[0];
$columns = $resolver->resolve($select); // ['id', 'name', 'email']
```

## 8. Traversal & transformation — `Traversal\*`

All utilities accept `Ast\Node` (statements, expressions, clause nodes).
**None accept `Ast\Script`** — iterate `$script->statements`.

### 8.1 `NodeChildExtractor` (final, static)

```php
public static function children(Node $node): array;   // list<Node>, direct children
public static function hasNodes(mixed $value): bool;  // any Node at any depth in value
```

Reflection over public initialized properties, in declaration order; collects
Node values and recursively flattens arrays containing Nodes (e.g. INSERT
`rows`). Scalars are skipped.

### 8.2 `NodeFinder` (final) — pre-order search

```php
public function __construct(string|\Closure $predicate); // class-string<Node> or Closure(Node): bool
public static function byClass(string $class): self;
public static function byPredicate(\Closure $predicate): self;
public function findAll(Node $root): array;   // list<Node>, includes root if it matches
public function findFirst(Node $root): ?Node;
public function contains(Node $root): bool;
```

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Traversal\NodeFinder;

$stmt = $script->statements[0];
$refs = (new NodeFinder(ColumnReference::class))->findAll($stmt);
$firstId = (new NodeFinder(
    static fn($n): bool => $n instanceof ColumnReference && $n->name === 'id'
))->findFirst($stmt);
```

### 8.3 `NodeTraverser` + `NodeVisitor` — read-only walking

```php
final class NodeTraverser {
    public function addVisitor(NodeVisitor $visitor): void;
    public function getVisitors(): array;      // list<NodeVisitor>
    public function traverse(Node $root): void;
}
abstract class NodeVisitor {
    public function enterNode(Node $node): void;  // pre-order, before children
    public function leaveNode(Node $node): void;  // post-order, after children
}
```

Order: for each node — all visitors' `enterNode` (in registration order), then
children, then all visitors' `leaveNode`.

```php
final class ColumnCollector extends \Inilim\SqliteParser\Traversal\NodeVisitor {
    public array $columns = [];
    public function enterNode(\Inilim\SqliteParser\Ast\Node $node): void {
        if ($node instanceof \Inilim\SqliteParser\Ast\Expression\ColumnReference) {
            $this->columns[] = $node;
        }
    }
}
$collector = new ColumnCollector();
$t = new \Inilim\SqliteParser\Traversal\NodeTraverser();
$t->addVisitor($collector);
$t->traverse($script->statements[0]);
```

### 8.4 `NodeTransformer` (final) — post-order rewriting

```php
public function __construct(\Closure $transformer);  // Closure(Node): ?Node
public function transform(Node $root): ?Node;
```

Callback contract:
- return the **same instance** → keep unchanged;
- return a **different Node** → replace;
- return **null** → remove: nullable property becomes `null`; array element is
  dropped (lists reindexed); removing a required non-nullable property throws
  `\LogicException` (delete the parent node instead).

Children are transformed first (post-order); changed parents are rebuilt via
`NodeCloner`. The root itself may be removed (result `null`).

### 8.5 `NodeCloner` (final, static) — copy-with-overrides

```php
public static function with(Node $node, array $overrides): Node; // array<string,mixed>
```

Rebuilds the node through its constructor (works because every node uses
promoted ctor properties whose names match parameter names). Override keys are
constructor parameter names; unknown keys are ignored. Throws
`\RuntimeException` if the class has no constructor. Nodes without own
constructor parameters (`Star`, `EmptyStatement`, `InsertDefaultBody`) are
re-instantiated as-is.

```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Traversal\NodeCloner;
use Inilim\SqliteParser\Traversal\NodeTransformer;

// rename column references old -> new
$rename = new NodeTransformer(
    static fn($n) => $n instanceof ColumnReference && $n->name === 'old'
        ? NodeCloner::with($n, ['name' => 'new'])
        : $n
);
$newStmt = $rename->transform($script->statements[0]);

// drop WHERE from a SELECT
$dropWhere = new NodeTransformer(
    static fn($n) => $n instanceof \Inilim\SqliteParser\Ast\Statement\SelectStatement && $n->where !== null
        ? NodeCloner::with($n, ['where' => null])
        : $n
);
```

## 9. Supported SQL (dialect coverage)

### SELECT
- Expressions, literals, column references (plain and qualified).
- Functions and aggregates (incl. `DISTINCT` inside aggregates).
- `CAST`, `CASE` (searched and simple).
- Comparison, arithmetic, concatenation, logical, bitwise operators.
- `BETWEEN`, `IN` (list and subquery), `IS [NOT] NULL`, `LIKE`, `GLOB`.
- Scalar subqueries; derived tables in FROM (with alias and column aliases).
- JOIN forms: `NATURAL`, `INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`, `ON`, `USING`.
- `WHERE`, `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT`, `OFFSET` (int literals).
- `DISTINCT` / `ALL`.
- Compound selects: `UNION`, `UNION ALL`, `INTERSECT`, `EXCEPT`.
- CTEs: `WITH [RECURSIVE]` (on SELECT / compound SELECT only).
- Window functions: `OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE ...)` and
  named windows (`OVER w`).

### DML
- `INSERT`: `OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK`; multi-row `VALUES`;
  `INSERT ... SELECT`; `DEFAULT VALUES`; `RETURNING`.
- `UPDATE`: `OR ...` actions, `SET`, `WHERE`, `RETURNING`.
- `DELETE`: `WHERE`, `RETURNING`.
- UPSERT: `ON CONFLICT [(cols)] DO UPDATE SET ... [WHERE ...]` / `DO NOTHING`.

### DDL
- `CREATE TABLE`: `TEMP|TEMPORARY`, `IF NOT EXISTS`, column definitions,
  column constraints (`PRIMARY KEY [ASC|DESC] [AUTOINCREMENT]`, `NOT NULL`,
  `UNIQUE`, `CHECK`, `DEFAULT`, `COLLATE`, `REFERENCES`), table constraints
  (`PRIMARY KEY`, `UNIQUE`, `CHECK`, `FOREIGN KEY`, optional `CONSTRAINT name`),
  `ON CONFLICT` actions, FK `ON DELETE/UPDATE` actions, `WITHOUT ROWID`,
  `STRICT`, `CREATE TABLE ... AS SELECT`.
- `CREATE [UNIQUE] INDEX [IF NOT EXISTS]`: column/expression indexes, partial
  indexes with `WHERE`.
- `CREATE [TEMP] VIEW [IF NOT EXISTS]` with optional column list.
- `CREATE [TEMP] TRIGGER [IF NOT EXISTS]`: `BEFORE|AFTER|INSTEAD OF`,
  `INSERT|UPDATE [OF cols]|DELETE`, `FOR EACH ROW`, `WHEN`, `BEGIN ... END` body.
- `ALTER TABLE`: `RENAME TO`, `RENAME [COLUMN] old TO new`,
  `ADD [COLUMN] def`, `DROP [COLUMN] name`.
- `DROP TABLE|INDEX|VIEW|TRIGGER [IF EXISTS] name`.

### Utility statements
- `PRAGMA` in all six forms (bare / schema-qualified × no value / `= value` /
  `(value)`).
- `ATTACH [DATABASE] expr AS schema`, `DETACH [DATABASE] schema`.
- Transactions: `BEGIN [DEFERRED|IMMEDIATE|EXCLUSIVE] [TRANSACTION [name]]`,
  `COMMIT [TRANSACTION [name]]`, `END TRANSACTION [name]`,
  `ROLLBACK [TRANSACTION]`, `ROLLBACK TO [SAVEPOINT] name`,
  `SAVEPOINT name`, `RELEASE [SAVEPOINT] name`.
- `EXPLAIN statement`, `EXPLAIN QUERY PLAN statement`.

### JSON1
Parsed as ordinary nodes — no special JSON AST:
- Functions as `FunctionCall`: `json`, `json_object`, `json_array`, `json_quote`,
  `json_patch`, `json_extract`, `json_type`, `json_valid`, `json_array_length`,
  `json_error_position`, `json_insert`, `json_replace`, `json_set`,
  `json_remove`, `json_group_array`, `json_group_object`.
- `json_each` / `json_tree` as table-valued functions in FROM
  (`TableReference`).
- Operators `->` and `->>` as `BinaryOperation`.

## 10. Known limitations & API traps (read before writing code)

Parser limitations:
1. Bare `END;` is **not supported** (ambiguous with trigger bodies and CASE).
   Use `COMMIT` or `END TRANSACTION [name]`.
2. Functions with keyword-like names (`REPLACE`, `LEFT`, `RIGHT`) are not
   supported as `FunctionCall`.
3. Parameterized CAST types (`CAST(x AS VARCHAR(10))`) are not supported —
   single type word only.
4. `WITH ... INSERT/UPDATE/DELETE` is not parsed (DML nodes have no `with`).
5. `LIMIT`/`OFFSET` accept integer literals only (AST stores ints).
6. `UPDATE ... FROM` (join-update) is not supported.
7. `LIKE ... ESCAPE ...` is not supported.
8. Syntactic parsing only — no semantic/type/schema validation.

AST/API traps:
9. `Ast\Script` is **not** an `Ast\Node`: never pass it to `SqlPrinter`,
   `NodeDumper`, `NodeFinder`, `NodeTraverser`, `NodeTransformer`. Iterate
   `$script->statements`. (Some README examples show `$finder->findAll($script)`
   or `$printer->print($script)` — those are outdated and will fail.)
10. `Node` has no `attributes` property; use `jsonSerialize()` / `json_encode`.
    `Script` does have `attributes`, but it is a constructor-time snapshot —
    mutating `$script->statements` does not update it.
11. All node properties are readonly **except** `OrderBy` (`expression`, `desc`
    are mutable) and `Script`. To "modify" any other node use
    `NodeCloner::with()` / `NodeTransformer`.
12. `Star` stores no qualifier: `t.*` ≡ `*` in the AST and in the resolver.
13. `ExpressionList` and `WhenClause` extend `Node`, **not** `Expression` —
    keep this in mind for `instanceof Expression` checks.
14. `InsertStatement` body: exactly one of `rows` / `select` /
    `defaultValues=true`. `InExpression`: `list` XOR `subquery`.
    `WindowFunction`: `specification` XOR `windowName`.
    `TableReference`: `name` XOR `subquery`.
    `UpsertClause`: `assignments === null` means DO NOTHING.
15. `CompoundSelect`: `count(operators) === count(selects) - 1`; trailing
    ORDER BY/LIMIT live on the compound, not on member selects.
16. `SqlPrinter` does not preserve comments/formatting, adds no trailing `;`,
    normalizes some forms (§5), and its parenthesization only considers
    BinaryOperation children — exotic nestings may need manual parens.
17. `ColumnResolver` limitations: see §7.3 (t.*, recursive CTEs, unknown
    tables, EXPLAIN unwrapping).
18. PHP floor is **8.4** (composer.json), not 8.1 as README claims.

## 11. Grammar development (only when modifying the library itself)

- Grammar lives in `grammar/*.pp3`: `sqlite.pp3` (root), `tokens.pp3`,
  `statement.pp3`, `select.pp3`, `expression.pp3`, `dml.pp3`, `ddl.pp3`,
  `utility.pp3`.
- Generated parser: `src/Parser/SqliteParser.php` — **never edit manually**;
  regenerate after grammar changes.
- `Grammar\Reducers` (final, static utilities for PP3 reducer actions):
  - `normalizeRepeat(mixed $rest): array` — flattens any phplrt repetition
    result shape (flat / nested / single / value-list) into `list<mixed>`.
  - `foldLeftBinaryChain(Expression $left, mixed $rest, ?string $defaultOperator = null): Expression`
    — folds a repetition into a left-associative `BinaryOperation` chain.
    Pass **all** children from index 1 (`array_slice($children, 1)`), not just
    `$children[1]`. With `$defaultOperator` set (e.g. `'AND'`, `'OR'`) the rest
    is treated as a value list; otherwise as op/value pairs.
- Composer scripts:
  - `composer grammar:check` — validate grammar (`phplrt check`).
  - `composer grammar:build` — regenerate `src/Parser/SqliteParser.php`.
  - `composer grammar:diff` — build + fail if generated parser drifted.
  - `composer tests` — PHPUnit.
  - `composer stan` — PHPStan level 8.
  - `composer ci` — check + diff + tests + stan.
- Tests: `tests/Grammar/` (parser/grammar), `tests/Ast/`, `tests/Parser/`,
  `tests/Ported/` (round-trip, boundaries, invalid-SQL rejection, etc.).
  Test namespace: `Inilim\Test\`.

## 12. Copy-paste recipes

### Parse and inspect
```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;

$script = (new Parser())->parse('SELECT id, name FROM users WHERE active = 1;');
$select = $script->statements[0];
if ($select instanceof SelectStatement) {
    foreach ($select->columns as $rc) {
        if ($rc->expression instanceof ColumnReference) {
            echo $rc->expression->name, PHP_EOL;
        }
    }
}
```

### Print back to SQL
```php
use Inilim\SqliteParser\Printer\SqlPrinter;

$printer = new SqlPrinter();
$sql = implode(";\n", array_map(
    static fn($stmt): string => $printer->print($stmt),
    $script->statements
)) . ';';
```

### JSON snapshot
```php
$json = json_encode($script, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$nodeArray = $script->statements[0]->jsonSerialize();
```

### Resolve output columns
```php
use Inilim\SqliteParser\Resolver\ArraySchema;
use Inilim\SqliteParser\Resolver\ColumnResolver;

$schema = new ArraySchema(['users' => ['id', 'name', 'email']]);
$names = (new ColumnResolver($schema))->resolve($script->statements[0]);
```

### Collect all column references (finder or visitor)
```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Traversal\NodeFinder;

$refs = (new NodeFinder(ColumnReference::class))->findAll($script->statements[0]);
```

### Rewrite: rename a column everywhere in a statement
```php
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Traversal\NodeCloner;
use Inilim\SqliteParser\Traversal\NodeTransformer;

$t = new NodeTransformer(
    static fn($n) => $n instanceof ColumnReference && $n->name === 'old'
        ? NodeCloner::with($n, ['name' => 'new'])
        : $n
);
$newStmt = $t->transform($script->statements[0]);
```

### Build AST manually and print
```php
use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Printer\SqlPrinter;

$select = new SelectStatement(
    columns: [new ResultColumn(new ColumnReference('id'))],
    from: new TableReference(name: 'users'),
    where: new BinaryOperation('=', new ColumnReference('active'), new Literal(1, 'int')),
);
echo (new SqlPrinter())->print($select); // SELECT id FROM users WHERE active = 1
```

### JSON1 operators
```php
$script = (new Parser())->parse("SELECT data->>'$.x' AS x FROM t;");
// ResultColumn->expression is BinaryOperation('->>', ColumnReference('data'), Literal('$.x','string'))
```

### Parse from a file (low-level)
```php
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\FileSource;

$script = (new SqliteParser())->parse(FileSource::createFromPath('schema.sql'));
```

## 13. File map

```text
src/
  Ast/
    Node.php                 abstract base (JsonSerializable, no attributes)
    Script.php               root container (NOT a Node, mutable)
    Statement.php            abstract statement base
    Expression.php           abstract expression base
    Expression/              16 expression nodes (§4.1)
    Statement/               18 statements + 31 clause/helper nodes (§4.2–4.4)
  Grammar/Reducers.php       PP3 reducer utilities (§11)
  Parser/
    Parser.php               string wrapper (§3.1)
    SqliteParser.php         GENERATED low-level parser — do not edit (§3.2)
  Printer/
    SqlPrinter.php           AST → SQL (§5)
    NodeDumper.php           AST → debug text (§6)
  Resolver/
    SchemaProvider.php       interface (§7.1)
    ArraySchema.php          array-backed schema (§7.2)
    ColumnResolver.php       output column names (§7.3)
  Traversal/
    NodeChildExtractor.php   direct children (§8.1)
    NodeFinder.php           pre-order search (§8.2)
    NodeTraverser.php        visitor walking (§8.3)
    NodeVisitor.php          visitor base (§8.3)
    NodeTransformer.php      post-order rewriting (§8.4)
    NodeCloner.php           copy-with-overrides (§8.5)
grammar/*.pp3                grammar sources
tests/                       PHPUnit tests (Inilim\Test\)
```

## 14. Common imports cheat sheet

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Parser\SqliteParser;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\FunctionCall;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Printer\SqlPrinter;
use Inilim\SqliteParser\Printer\NodeDumper;
use Inilim\SqliteParser\Resolver\SchemaProvider;
use Inilim\SqliteParser\Resolver\ArraySchema;
use Inilim\SqliteParser\Resolver\ColumnResolver;
use Inilim\SqliteParser\Traversal\NodeChildExtractor;
use Inilim\SqliteParser\Traversal\NodeCloner;
use Inilim\SqliteParser\Traversal\NodeFinder;
use Inilim\SqliteParser\Traversal\NodeTransformer;
use Inilim\SqliteParser\Traversal\NodeTraverser;
use Inilim\SqliteParser\Traversal\NodeVisitor;
```