<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

use JsonSerializable;

/**
 * Корневой узел AST — результат разбора SQL-скрипта.
 *
 * Содержит плоский список инструкций в порядке их появления в исходном SQL.
 * Реализует {@see JsonSerializable} для удобной сериализации AST целиком
 * (см. тесты tests/Ast/ScriptTest.php и tests/Parser/SqliteParserTest.php).
 */
class Script implements JsonSerializable
{
    /**
     * Инструкции скрипта в порядке их появления в исходном SQL.
     *
     * @var list<Statement>
     */
    public array $statements;

    /**
     * Атрибуты узла для сериализации и отладки.
     *
     * Ключ `attributes` намеренно НЕ кладётся в этот массив, чтобы
     * избежать бесконечной рекурсии при автоматической сериализации
     * (см. testAttributesDoesNotContainItself).
     *
     * @var array{statements: list<Statement>}
     */
    public array $attributes;

    /**
     * @param list<Statement> $statements
     */
    public function __construct(array $statements = [])
    {
        $this->statements = $statements;
        $this->attributes = ['statements' => $statements];
    }

    /**
     * @return array{statements: list<Statement>}
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}