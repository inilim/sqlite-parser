<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Statement\CreateIndexStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\CreateViewStatement;
use Inilim\SqliteParser\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Исчерпывающие тесты DDL: CREATE TABLE / ALTER / CREATE INDEX / CREATE VIEW.
 *
 * Покрывает:
 *   - РЕГРЕССИЮ бага DEFAULT CURRENT_TIMESTAMP (падение на example_complex.php);
 *   - все разумные комбинации типов колонок × column constraints;
 *   - table-level constraints (PRIMARY KEY / UNIQUE / CHECK / FOREIGN KEY);
 *   - опции таблицы (WITHOUT ROWID / STRICT);
 *   - разнообразные FOREIGN KEY-действия;
 *   - CREATE INDEX (в т.ч. UNIQUE, IF NOT EXISTS, WHERE);
 *   - CREATE VIEW;
 *   - ALTER TABLE.
 */
final class DdlComprehensiveTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    // ========================================================================
    // Регрессия: DEFAULT CURRENT_TIMESTAMP (падал в example_complex.php)
    // ========================================================================

    public function testSandboxSchemaFromExampleParses(): void
    {
        $schemaSql = <<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    parent_id INTEGER
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    category_id INTEGER,
    price REAL,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE orders (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    status TEXT DEFAULT 'new',
    total REAL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    price REAL NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE reviews (
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rating INTEGER,
    comment TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
SQL;

        $script = $this->parser->parse($schemaSql);
        self::assertCount(6, $script->statements);
        foreach ($script->statements as $stmt) {
            self::assertInstanceOf(CreateTableStatement::class, $stmt);
        }
    }

    // ========================================================================
    // DEFAULT: все поддерживаемые формы
    // ========================================================================

    /**
     * @dataProvider provideDefaultValueClauses
     */
    public function testDefaultValueForms(string $defaultClause): void
    {
        $sql = "CREATE TABLE t (c TEXT {$defaultClause});";
        $script = $this->parser->parse($sql);

        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertCount(1, $stmt->columns);

        $constraints = $stmt->columns[0]->constraints;
        self::assertCount(1, $constraints);
        self::assertSame('DEFAULT', $constraints[0]->type);
    }

    public static function provideDefaultValueClauses(): iterable
    {
        // Литералы
        yield 'integer 0' => ['DEFAULT 0'];
        yield 'integer positive' => ['DEFAULT 42'];
        yield 'integer negative' => ['DEFAULT -1'];
        yield 'integer hex' => ['DEFAULT 0xFF'];
        yield 'integer binary' => ['DEFAULT 0b1010'];
        yield 'float' => ['DEFAULT 3.14'];
        yield 'float negative' => ['DEFAULT -2.5'];
        yield 'float scientific' => ['DEFAULT 1e10'];
        yield 'float scientific signed' => ['DEFAULT -1.5e-3'];
        yield 'string simple' => ["DEFAULT 'hello'"];
        yield 'string empty' => ["DEFAULT ''"];
        yield 'string with escaped quote' => ["DEFAULT 'it''s ok'"];
        yield 'null' => ['DEFAULT NULL'];
        yield 'true' => ['DEFAULT TRUE'];
        yield 'false' => ['DEFAULT FALSE'];

        // Спец-литералы SQLite: разбираются как T_IDENT → ColumnReference
        yield 'CURRENT_TIME' => ['DEFAULT CURRENT_TIME'];
        yield 'CURRENT_DATE' => ['DEFAULT CURRENT_DATE'];
        yield 'CURRENT_TIMESTAMP' => ['DEFAULT CURRENT_TIMESTAMP'];

        // Скобки
        yield 'paren int' => ['DEFAULT (1)'];
        yield 'paren string' => ["DEFAULT ('x')"];
        yield 'paren expr' => ['DEFAULT (1 + 2)'];

        // Выражения и функции
        yield 'arith add' => ['DEFAULT 1 + 2'];
        yield 'arith mul' => ['DEFAULT 2 * 3'];
        yield 'arith mixed' => ['DEFAULT 1 + 2 * 3'];
        yield 'function datetime' => ["DEFAULT datetime('now')"];
        yield 'function strftime' => ["DEFAULT strftime('%s', 'now')"];
    }

    // ========================================================================
    // Column constraints: все варианты
    // ========================================================================

    /**
     * @dataProvider provideColumnConstraintSequences
     */
    public function testColumnConstraintSequences(string $constraints): void
    {
        $sql = "CREATE TABLE t (c INTEGER {$constraints});";
        $script = $this->parser->parse($sql);

        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertCount(1, $stmt->columns);
    }

    public static function provideColumnConstraintSequences(): iterable
    {
        // Одиночные
        yield 'not null' => ['NOT NULL'];
        yield 'unique' => ['UNIQUE'];
        yield 'primary key' => ['PRIMARY KEY'];
        yield 'primary key asc' => ['PRIMARY KEY ASC'];
        yield 'primary key desc' => ['PRIMARY KEY DESC'];
        yield 'primary key autoincrement' => ['PRIMARY KEY AUTOINCREMENT'];
        yield 'primary key asc autoincrement' => ['PRIMARY KEY ASC AUTOINCREMENT'];
        yield 'primary key desc autoincrement' => ['PRIMARY KEY DESC AUTOINCREMENT'];
        yield 'check' => ['CHECK (c > 0)'];
        yield 'default 0' => ['DEFAULT 0'];
        yield 'default current timestamp' => ['DEFAULT CURRENT_TIMESTAMP'];
        yield 'default current date' => ['DEFAULT CURRENT_DATE'];
        yield 'collate nocase' => ['COLLATE NOCASE'];
        yield 'collate binary' => ['COLLATE BINARY'];

        // Комбинации из двух
        yield 'not null unique' => ['NOT NULL UNIQUE'];
        yield 'unique not null' => ['UNIQUE NOT NULL'];
        yield 'not null default 0' => ['NOT NULL DEFAULT 0'];
        yield 'not null default current_timestamp' => ['NOT NULL DEFAULT CURRENT_TIMESTAMP'];
        yield 'default current_timestamp not null' => ['DEFAULT CURRENT_TIMESTAMP NOT NULL'];
        yield 'default 0 not null' => ['DEFAULT 0 NOT NULL'];
        yield 'unique default 0' => ['UNIQUE DEFAULT 0'];
        yield 'unique default current_timestamp' => ['UNIQUE DEFAULT CURRENT_TIMESTAMP'];
        yield 'not null check' => ['NOT NULL CHECK (c > 0)'];
        yield 'collate nocase not null' => ['COLLATE NOCASE NOT NULL'];
        yield 'primary key not null' => ['PRIMARY KEY NOT NULL'];

        // Комбинации из трёх+
        yield 'not null unique default 0' => ['NOT NULL UNIQUE DEFAULT 0'];
        yield 'not null unique default current_timestamp' => ['NOT NULL UNIQUE DEFAULT CURRENT_TIMESTAMP'];
        yield 'not null unique check' => ['NOT NULL UNIQUE CHECK (c > 0)'];
        yield 'not null default 0 unique' => ['NOT NULL DEFAULT 0 UNIQUE'];
        yield 'primary key not null unique' => ['PRIMARY KEY NOT NULL UNIQUE'];
        yield 'primary key not null unique check' => ['PRIMARY KEY NOT NULL UNIQUE CHECK (c > 0)'];
        yield 'not null unique default 0 check' => ['NOT NULL UNIQUE DEFAULT 0 CHECK (c >= 0)'];
    }

    // ========================================================================
    // Матрица type × constraint
    // ========================================================================

    /**
     * @dataProvider provideTypeConstraintsMatrix
     */
    public function testTypeConstraintsMatrix(string $columnDefinition): void
    {
        $sql = "CREATE TABLE t ({$columnDefinition});";
        $script = $this->parser->parse($sql);

        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertCount(1, $stmt->columns);
    }

    public static function provideTypeConstraintsMatrix(): iterable
    {
        $types = [
            'INTEGER', 'INT', 'TEXT', 'REAL', 'BLOB', 'NUMERIC', 'BOOLEAN',
            'VARCHAR(255)', 'DECIMAL(10,2)', 'DATE', 'DATETIME', 'TIMESTAMP',
            'CHAR(50)',
        ];

        $constraints = [
            '',
            'NOT NULL',
            'UNIQUE',
            'PRIMARY KEY',
            'PRIMARY KEY AUTOINCREMENT',
            'NOT NULL UNIQUE',
            'DEFAULT 0',
            'DEFAULT NULL',
            "DEFAULT 'x'",
            'DEFAULT CURRENT_TIMESTAMP',
            'NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'NOT NULL UNIQUE DEFAULT 0',
            'CHECK (c > 0)',
            'NOT NULL CHECK (c > 0)',
            'COLLATE NOCASE',
        ];

        foreach ($types as $type) {
            foreach ($constraints as $c) {
                $def = "c {$type}" . ($c !== '' ? " {$c}" : '');
                yield $def => [$def];
            }
        }
    }

    // ========================================================================
    // Table-level constraints
    // ========================================================================

    /**
     * @dataProvider provideTableLevelConstraints
     */
    public function testTableLevelConstraints(string $body): void
    {
        $sql = "CREATE TABLE t ({$body});";
        $script = $this->parser->parse($sql);

        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertNotEmpty($stmt->tableConstraints);
    }

    public static function provideTableLevelConstraints(): iterable
    {
        yield 'pk single' => ['a INT, PRIMARY KEY (a)'];
        yield 'pk composite' => ['a INT, b INT, PRIMARY KEY (a, b)'];
        yield 'unique single' => ['a INT, UNIQUE (a)'];
        yield 'unique composite' => ['a INT, b INT, UNIQUE (a, b)'];
        yield 'check table' => ['a INT, CHECK (a > 0)'];
        yield 'named pk' => ['a INT, CONSTRAINT pk PRIMARY KEY (a)'];
        yield 'named unique' => ['a INT, CONSTRAINT uq UNIQUE (a)'];
        yield 'fk simple' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id)'];
        yield 'fk no columns' => ['a INT, FOREIGN KEY (a) REFERENCES t2'];
        yield 'fk cascade delete' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON DELETE CASCADE'];
        yield 'fk cascade update' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON UPDATE CASCADE'];
        yield 'fk set null' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON DELETE SET NULL'];
        yield 'fk set default' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON DELETE SET DEFAULT'];
        yield 'fk no action' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON UPDATE NO ACTION'];
        yield 'fk restrict' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON DELETE RESTRICT'];
        yield 'fk both' => ['a INT, FOREIGN KEY (a) REFERENCES t2 (id) ON DELETE CASCADE ON UPDATE CASCADE'];
    }

    // ========================================================================
    // Многоколоночные таблицы
    // ========================================================================

    /**
     * @dataProvider provideMultiColumnTables
     *
     * @param list<string> $columns
     */
    public function testMultiColumnTables(array $columns): void
    {
        $body = implode(', ', $columns);
        $sql = "CREATE TABLE t ({$body});";

        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertCount(count($columns), $stmt->columns);
    }

    public static function provideMultiColumnTables(): iterable
    {
        yield 'two simple' => [['a INT', 'b TEXT']];
        yield 'five simple' => [['a INT', 'b INT', 'c INT', 'd INT', 'e INT']];
        yield 'pk + others' => [['id INTEGER PRIMARY KEY', 'a INT', 'b TEXT']];
        yield 'pk autoinc + text' => [['id INTEGER PRIMARY KEY AUTOINCREMENT', 'name TEXT NOT NULL']];
        yield 'mixed defaults' => [
            ['a INT DEFAULT 0', 'b TEXT DEFAULT CURRENT_TIMESTAMP', "c TEXT DEFAULT 'x'"],
        ];
        yield 'multiple checks' => [['a INT CHECK (a > 0)', 'b INT CHECK (b < 100)']];
        yield 'all literals as defaults' => [
            [
                'a INT DEFAULT 0',
                "b TEXT DEFAULT 'x'",
                'c REAL DEFAULT 3.14',
                'd TEXT DEFAULT NULL',
                'e TEXT DEFAULT CURRENT_TIMESTAMP',
            ],
        ];
    }

    // ========================================================================
    // Опции таблицы
    // ========================================================================

    /**
     * @dataProvider provideTableOptions
     */
    public function testTableOptions(string $sql, bool $expectWithoutRowid, bool $expectStrict): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertSame($expectWithoutRowid, $stmt->withoutRowid);
        self::assertSame($expectStrict, $stmt->strict);
    }

    public static function provideTableOptions(): iterable
    {
        yield 'plain' => ['CREATE TABLE t (a INT);', false, false];
        yield 'without rowid' => ['CREATE TABLE t (a INT) WITHOUT ROWID;', true, false];
        yield 'strict' => ['CREATE TABLE t (a INT) STRICT;', false, true];
        yield 'strict with comma' => ['CREATE TABLE t (a INT), STRICT;', false, true];
        yield 'without rowid strict' => ['CREATE TABLE t (a INT) WITHOUT ROWID, STRICT;', true, true];
    }

    // ========================================================================
    // CREATE TABLE AS SELECT
    // ========================================================================

    /**
     * @dataProvider provideCreateTableAsSelect
     */
    public function testCreateTableAsSelect(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateTableStatement::class, $stmt);
        self::assertNotNull($stmt->asSelect);
    }

    public static function provideCreateTableAsSelect(): iterable
    {
        yield 'literal' => ['CREATE TABLE t AS SELECT 1;'];
        yield 'columns' => ['CREATE TABLE t AS SELECT a, b FROM u;'];
        yield 'with where' => ['CREATE TABLE t AS SELECT * FROM u WHERE a > 0;'];
    }

    // ========================================================================
    // CREATE INDEX
    // ========================================================================

    /**
     * @dataProvider provideCreateIndex
     */
    public function testCreateIndex(string $sql, bool $unique, bool $ifNotExists): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateIndexStatement::class, $stmt);
        self::assertSame($unique, $stmt->unique);
        self::assertSame($ifNotExists, $stmt->ifNotExists);
    }

    public static function provideCreateIndex(): iterable
    {
        yield 'plain' => ['CREATE INDEX i ON t (a);', false, false];
        yield 'unique' => ['CREATE UNIQUE INDEX i ON t (a);', true, false];
        yield 'if not exists' => ['CREATE INDEX IF NOT EXISTS i ON t (a);', false, true];
        yield 'unique if not exists' => ['CREATE UNIQUE INDEX IF NOT EXISTS i ON t (a);', true, true];
        yield 'multi column' => ['CREATE INDEX i ON t (a, b, c);', false, false];
        yield 'with where' => ['CREATE INDEX i ON t (a) WHERE a > 0;', false, false];
    }

    // ========================================================================
    // CREATE VIEW
    // ========================================================================

    /**
     * @dataProvider provideCreateView
     */
    public function testCreateView(string $sql): void
    {
        $script = $this->parser->parse($sql);
        self::assertCount(1, $script->statements);
        $stmt = $script->statements[0];
        self::assertInstanceOf(CreateViewStatement::class, $stmt);
    }

    public static function provideCreateView(): iterable
    {
        yield 'simple' => ['CREATE VIEW v AS SELECT 1;'];
        yield 'columns' => ['CREATE VIEW v AS SELECT a, b FROM u;'];
        yield 'explicit columns' => ['CREATE VIEW v (x, y) AS SELECT a, b FROM u;'];
        yield 'temp' => ['CREATE TEMP VIEW v AS SELECT 1;'];
        yield 'if not exists' => ['CREATE VIEW IF NOT EXISTS v AS SELECT 1;'];
        yield 'where' => ['CREATE VIEW v AS SELECT * FROM u WHERE a > 0;'];
    }
}