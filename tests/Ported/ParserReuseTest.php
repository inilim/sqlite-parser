<?php

declare(strict_types=1);

namespace Inilim\Test\Ported;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Parser\SqliteParser;
use PHPUnit\Framework\TestCase;
use Phplrt\Source\StringSource;
use Throwable;

/**
 * Порт `cursor_coexistence.rs` + `smoke.rs::parser_reuse`.
 *
 * В Rust проверяется, что парсер и порождённая им сессия могут жить в одной
 * структуре, а парсер можно переиспользовать после освобождения сессии.
 * У нас нет курсорной модели, поэтому инвариант переформулирован так:
 * один экземпляр парсера многократно парсит разные скрипты и каждый раз
 * возвращает независимое корректное AST.
 */
final class ParserReuseTest extends TestCase
{
    public function testStringParserReuseWithDifferentStatementKinds(): void
    {
        $parser = new Parser();

        $select = $parser->parse('SELECT 1');
        self::assertInstanceOf(Script::class, $select);
        self::assertCount(1, $select->statements);
        self::assertInstanceOf(SelectStatement::class, $select->statements[0]);

        $delete = $parser->parse('DELETE FROM t');
        self::assertCount(1, $delete->statements);
        self::assertInstanceOf(DeleteStatement::class, $delete->statements[0]);

        $insert = $parser->parse('INSERT INTO t (a) VALUES (1)');
        self::assertCount(1, $insert->statements);
        self::assertInstanceOf(InsertStatement::class, $insert->statements[0]);

        $update = $parser->parse('UPDATE t SET a = 1');
        self::assertCount(1, $update->statements);
        self::assertInstanceOf(UpdateStatement::class, $update->statements[0]);
    }

    public function testRepeatedParseProducesIndependentAsts(): void
    {
        $parser = new Parser();

        $a = $parser->parse('SELECT 1;');
        $b = $parser->parse('SELECT 2;');

        self::assertNotSame($a, $b);
        self::assertNotSame($a->statements, $b->statements);
        self::assertNotSame($a->statements[0], $b->statements[0]);

        // Первый AST не должен мутировать при последующих разборах.
        self::assertCount(1, $a->statements);
        self::assertInstanceOf(SelectStatement::class, $a->statements[0]);
    }

    public function testRepeatedParseOfSameSqlProducesEqualAst(): void
    {
        $parser = new Parser();

        $first = $parser->parse('SELECT id, name FROM users WHERE id = 1');
        $second = $parser->parse('SELECT id, name FROM users WHERE id = 1');

        self::assertSame(
            \json_encode($first, JSON_THROW_ON_ERROR),
            \json_encode($second, JSON_THROW_ON_ERROR),
        );
    }

    public function testLowLevelParserReuse(): void
    {
        $parser = new SqliteParser();

        for ($i = 0; $i < 5; $i++) {
            $ast = $parser->parse(StringSource::createFromString('SELECT ' . $i));
            self::assertInstanceOf(Script::class, $ast);
            self::assertCount(1, $ast->statements);
            self::assertInstanceOf(SelectStatement::class, $ast->statements[0]);
        }
    }

    public function testInjectedLowLevelParserIsReused(): void
    {
        $low = new SqliteParser();
        $parser = new Parser($low);

        $first = $parser->parse('SELECT 1;');
        $second = $parser->parse('SELECT 2; SELECT 3;');

        self::assertCount(1, $first->statements);
        self::assertCount(2, $second->statements);
    }

    public function testParserIsUsableAfterParseFailure(): void
    {
        $parser = new Parser();

        $failed = false;

        try {
            $parser->parse('SELECT');
        } catch (Throwable) {
            $failed = true;
        }

        self::assertTrue($failed, 'ожидали ошибку разбора для bare SELECT');

        // После исключения парсер обязан остаться рабочим.
        $script = $parser->parse('SELECT 1;');
        self::assertCount(1, $script->statements);
        self::assertInstanceOf(SelectStatement::class, $script->statements[0]);
    }

    public function testManySequentialParsesDoNotAccumulateState(): void
    {
        $parser = new Parser();

        $variants = [
            'SELECT 1',
            'SELECT a, b FROM t',
            'CREATE TABLE t (id INTEGER)',
            'INSERT INTO t (id) VALUES (1)',
            'UPDATE t SET id = 2',
            'DELETE FROM t',
            'DROP TABLE t',
            'PRAGMA user_version',
        ];

        for ($i = 0; $i < 40; $i++) {
            $sql = $variants[$i % \count($variants)];
            $script = $parser->parse($sql);

            self::assertCount(1, $script->statements, 'скрипт: ' . $sql);
        }
    }

    public function testSchemaAndQueryParsesAreIndependent(): void
    {
        $parser = new Parser();

        $schema = $parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);');
        self::assertCount(1, $schema->statements);

        $query = $parser->parse('SELECT id, name FROM users');
        self::assertCount(1, $query->statements);
        self::assertInstanceOf(SelectStatement::class, $query->statements[0]);

        // Разбор запроса не должен «подмешивать» инструкции из схемы.
        self::assertCount(1, $schema->statements);
    }
}