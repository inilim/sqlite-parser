<?php

declare(strict_types=1);

namespace Inilim\Test\Parser;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\EmptyStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Parser\SqliteParser;
use PHPUnit\Framework\TestCase;
use Phplrt\Source\StringSource;

/**
 * Смоук-тесты сгенерированного парсера.
 *
 * Важно: этот тест инстанцирует именно сгенерированный класс
 * {@see SqliteParser} (а не Compiler / builders), что является
 * критерием Definition of Done (пункт 5) для CI.
 */
final class SqliteParserTest extends TestCase
{
    private SqliteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqliteParser();
    }

    public function testInstantiatesGeneratedParser(): void
    {
        self::assertInstanceOf(SqliteParser::class, $this->parser);
    }

    public function testParsesEmptyInput(): void
    {
        $ast = $this->parser->parse(StringSource::createFromString(''));

        self::assertInstanceOf(Script::class, $ast);
        self::assertSame([], $ast->statements);
    }

    public function testParsesBareSemicolon(): void
    {
        $ast = $this->parser->parse(StringSource::createFromString(';'));

        self::assertInstanceOf(Script::class, $ast);
        self::assertCount(1, $ast->statements);
        self::assertInstanceOf(EmptyStatement::class, $ast->statements[0]);
    }

    public function testParsesSingleSelect(): void
    {
        $ast = $this->parser->parse(StringSource::createFromString('SELECT 1;'));

        self::assertInstanceOf(Script::class, $ast);
        self::assertCount(1, $ast->statements);
        self::assertInstanceOf(SelectStatement::class, $ast->statements[0]);
    }

    public function testAstIsJsonSerializable(): void
    {
        $ast = $this->parser->parse(StringSource::createFromString('SELECT 1;'));

        $json = \json_encode($ast);

        self::assertIsString($json);
        self::assertJson($json);
    }

    public function testParserProducesDistinctAstsForDistinctSql(): void
    {
        $a = $this->parser->parse(StringSource::createFromString('SELECT 1;'));
        $b = $this->parser->parse(StringSource::createFromString('SELECT 2;'));

        self::assertInstanceOf(Script::class, $a);
        self::assertInstanceOf(Script::class, $b);
        self::assertNotSame($a, $b);
    }
}