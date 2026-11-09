<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Parser;

use Inilim\SqliteParser\Ast\Script;
use Phplrt\Source\StringSource;

/**
 * Удобная обёртка над {@see SqliteParser}, позволяющая парсить SQL
 * прямо из строки, без ручного создания {@see StringSource}.
 *
 * Пример:
 *
 *     $parser = new \Inilim\SqliteParser\Parser\Parser();
 *     $script = $parser->parse('SELECT id FROM users;');
 *
 * Низкоуровневый {@see SqliteParser} продолжает работать и принимает
 * любую реализацию {@see \Phplrt\Contracts\Source\ReadableInterface}.
 */
final class Parser
{
    private readonly SqliteParser $parser;

    public function __construct(?SqliteParser $parser = null)
    {
        $this->parser = $parser ?? new SqliteParser();
    }

    /**
     * Разбирает SQL-скрипт из строки.
     *
     * @throws \Phplrt\Contracts\Lexer\Exception\LexerExceptionInterface
     * @throws \Phplrt\Contracts\Parser\Exception\ParserExceptionInterface
     */
    public function parse(string $sql): Script
    {
        /** @var Script $script */
        $script = $this->parser->parse(StringSource::createFromString($sql));

        return $script;
    }
}