<?php

declare(strict_types=1);

namespace Inilim\Test\Grammar;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\AlterTableStatement;
use Inilim\SqliteParser\Ast\Statement\ColumnConstraint;
use Inilim\SqliteParser\Ast\Statement\ColumnDefinition;
use Inilim\SqliteParser\Ast\Statement\CreateIndexStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\CreateViewStatement;
use Inilim\SqliteParser\Ast\Statement\DropStatement;
use Inilim\SqliteParser\Ast\Statement\TableConstraint;
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\TestCase;

final class DdlTest extends TestCase
{
    private SqliteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteParser();
    }

    /**
     * @return list<\Inilim\SqliteParser\Ast\Statement>
     */
    private function parse(string $sql): array
    {
        /** @var Script $script */
        $script = $this->parser->parse(StringSource::createFromString($sql));
        return $script->statements;
    }

    public function testCreateSimpleTable(): void
    {
        $stmts = $this->parse('CREATE TABLE users (id INTEGER, name TEXT);');
        $this->assertCount(1, $stmts);

        $stmt = $stmts[0];
        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $this->assertSame('users', $stmt->table);
        $this->assertCount(2, $stmt->columns);

        $this->assertInstanceOf(ColumnDefinition::class, $stmt->columns[0]);
        $this->assertSame('id', $stmt->columns[0]->name);
        $this->assertSame('INTEGER', $stmt->columns[0]->type);

        $this->assertSame('name', $stmt->columns[1]->name);
        $this->assertSame('TEXT', $stmt->columns[1]->type);
    }

    public function testCreateTableIfNotExistsTemporary(): void
    {
        $stmts = $this->parse('CREATE TEMP TABLE IF NOT EXISTS t (a INT);');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $this->assertTrue($stmt->temporary);
        $this->assertTrue($stmt->ifNotExists);
    }

    public function testCreateTablePrimaryKeyAutoincrement(): void
    {
        $stmts = $this->parse('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT);');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $col = $stmt->columns[0];
        $this->assertSame('id', $col->name);
        $this->assertCount(1, $col->constraints);

        $c = $col->constraints[0];
        $this->assertInstanceOf(ColumnConstraint::class, $c);
        $this->assertSame('PRIMARY_KEY', $c->type);
        $this->assertTrue($c->autoincrement);
    }

    public function testCreateTableNotNullUnique(): void
    {
        $stmts = $this->parse('CREATE TABLE t (a INT NOT NULL UNIQUE);');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $col = $stmt->columns[0];
        $this->assertCount(2, $col->constraints);
        $this->assertSame('NOT_NULL', $col->constraints[0]->type);
        $this->assertSame('UNIQUE', $col->constraints[1]->type);
    }

    public function testCreateTableCompositePrimaryKey(): void
    {
        $stmts = $this->parse('CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b));');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $this->assertCount(2, $stmt->columns);
        $this->assertCount(1, $stmt->tableConstraints);

        $tc = $stmt->tableConstraints[0];
        $this->assertInstanceOf(TableConstraint::class, $tc);
        $this->assertSame('PRIMARY_KEY', $tc->type);
        $this->assertSame(['a', 'b'], $tc->columns);
    }

    public function testCreateTableWithoutRowidStrict(): void
    {
        $stmts = $this->parse('CREATE TABLE t (a INT) WITHOUT ROWID;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $this->assertTrue($stmt->withoutRowid);
        $this->assertFalse($stmt->strict);
    }

    public function testCreateTableAsSelect(): void
    {
        $stmts = $this->parse('CREATE TABLE t AS SELECT 1;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateTableStatement::class, $stmt);
        $this->assertNotNull($stmt->asSelect);
    }

    public function testCreateIndex(): void
    {
        $stmts = $this->parse('CREATE INDEX idx_users_name ON users (name);');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateIndexStatement::class, $stmt);
        $this->assertSame('idx_users_name', $stmt->name);
        $this->assertSame('users', $stmt->table);
        $this->assertSame(['name'], $stmt->columns);
        $this->assertFalse($stmt->unique);
    }

    public function testCreateUniqueIndexIfNotExists(): void
    {
        $stmts = $this->parse('CREATE UNIQUE INDEX IF NOT EXISTS idx ON t (a, b);');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateIndexStatement::class, $stmt);
        $this->assertTrue($stmt->unique);
        $this->assertTrue($stmt->ifNotExists);
        $this->assertSame(['a', 'b'], $stmt->columns);
    }

    public function testCreateIndexWithWhere(): void
    {
        $stmts = $this->parse('CREATE INDEX idx ON t (a) WHERE a > 0;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateIndexStatement::class, $stmt);
        $this->assertNotNull($stmt->where);
    }

    public function testCreateView(): void
    {
        $stmts = $this->parse('CREATE VIEW v_users AS SELECT id, name FROM users;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateViewStatement::class, $stmt);
        $this->assertSame('v_users', $stmt->name);
        $this->assertNotNull($stmt->select);
    }

    public function testCreateViewTempIfNotExists(): void
    {
        $stmts = $this->parse('CREATE TEMP VIEW IF NOT EXISTS v AS SELECT 1;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(CreateViewStatement::class, $stmt);
        $this->assertTrue($stmt->temporary);
        $this->assertTrue($stmt->ifNotExists);
    }

    public function testAlterTableRenameTo(): void
    {
        $stmts = $this->parse('ALTER TABLE t RENAME TO t_new;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(AlterTableStatement::class, $stmt);
        $this->assertSame('t', $stmt->table);
        $this->assertSame('RENAME_TO', $stmt->action);
        $this->assertSame('t_new', $stmt->newName);
    }

    public function testAlterTableRenameColumn(): void
    {
        $stmts = $this->parse('ALTER TABLE t RENAME COLUMN old_col TO new_col;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(AlterTableStatement::class, $stmt);
        $this->assertSame('RENAME_COLUMN', $stmt->action);
        $this->assertSame('old_col', $stmt->oldName);
        $this->assertSame('new_col', $stmt->newName);
    }

    public function testAlterTableAddColumn(): void
    {
        $stmts = $this->parse('ALTER TABLE t ADD COLUMN new_col INT;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(AlterTableStatement::class, $stmt);
        $this->assertSame('ADD_COLUMN', $stmt->action);
        $this->assertNotNull($stmt->column);
        $this->assertSame('new_col', $stmt->column->name);
        $this->assertSame('INT', $stmt->column->type);
    }

    public function testAlterTableDropColumn(): void
    {
        $stmts = $this->parse('ALTER TABLE t DROP COLUMN old_col;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(AlterTableStatement::class, $stmt);
        $this->assertSame('DROP_COLUMN', $stmt->action);
        $this->assertSame('old_col', $stmt->oldName);
    }

    public function testDropTable(): void
    {
        $stmts = $this->parse('DROP TABLE users;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(DropStatement::class, $stmt);
        $this->assertSame('TABLE', $stmt->objectType);
        $this->assertSame('users', $stmt->name);
        $this->assertFalse($stmt->ifExists);
    }

    public function testDropTableIfExists(): void
    {
        $stmts = $this->parse('DROP TABLE IF EXISTS users;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(DropStatement::class, $stmt);
        $this->assertTrue($stmt->ifExists);
    }

    public function testDropIndex(): void
    {
        $stmts = $this->parse('DROP INDEX idx;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(DropStatement::class, $stmt);
        $this->assertSame('INDEX', $stmt->objectType);
        $this->assertSame('idx', $stmt->name);
    }

    public function testDropView(): void
    {
        $stmts = $this->parse('DROP VIEW v;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(DropStatement::class, $stmt);
        $this->assertSame('VIEW', $stmt->objectType);
    }

    public function testDropTrigger(): void
    {
        $stmts = $this->parse('DROP TRIGGER trg;');
        $stmt = $stmts[0];

        $this->assertInstanceOf(DropStatement::class, $stmt);
        $this->assertSame('TRIGGER', $stmt->objectType);
    }

    public function testMultipleDdlStatements(): void
    {
        $stmts = $this->parse(
            'CREATE TABLE a (id INT); CREATE INDEX i ON a (id); DROP TABLE a;'
        );
        $this->assertCount(3, $stmts);
        $this->assertInstanceOf(CreateTableStatement::class, $stmts[0]);
        $this->assertInstanceOf(CreateIndexStatement::class, $stmts[1]);
        $this->assertInstanceOf(DropStatement::class, $stmts[2]);
    }
}