<?php

declare(strict_types=1);

namespace Inilim\Test\Parser;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Parser\SqliteParser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testParseStringReturnsScript(): void
    {
        $parser = new Parser();
        $script = $parser->parse('SELECT id, name FROM users WHERE active = 1;');

        self::assertInstanceOf(Script::class, $script);
        self::assertCount(1, $script->statements);
        self::assertInstanceOf(SelectStatement::class, $script->statements[0]);
    }

    public function testParseEmptyStringReturnsEmptyScript(): void
    {
        $parser = new Parser();
        $script = $parser->parse('');

        self::assertInstanceOf(Script::class, $script);
        self::assertSame([], $script->statements);
    }

    public function testParseMultipleStatements(): void
    {
        $parser = new Parser();
        $script = $parser->parse('CREATE TABLE t (id INTEGER); INSERT INTO t VALUES (1);');

        self::assertInstanceOf(Script::class, $script);
        self::assertCount(2, $script->statements);
    }

    public function testCustomSqliteParserInjection(): void
    {
        $parser = new Parser(new SqliteParser());
        $script = $parser->parse('SELECT 1;');

        self::assertInstanceOf(Script::class, $script);
        self::assertCount(1, $script->statements);
    }
}