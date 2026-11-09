<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражение CAST: `CAST(expr AS type)`.
 *
 * $type хранится как строка в верхнем регистре: `INTEGER`, `TEXT`, `REAL`,
 * `BLOB`, `NUMERIC`. На этапе 4 не поддерживается параметризованный тип
 * (`VARCHAR(10)`) — только одно слово.
 */
final class CastExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly string $type,
    ) {
        parent::__construct();
    }
}