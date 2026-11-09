<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Вызов функции: `name(arg1, arg2, ...)`.
 *
 * Например:
 *   - `COUNT(*)`              → name='COUNT', arguments=[Star()], distinct=false
 *   - `COUNT(DISTINCT x)`     → name='COUNT', arguments=[ColumnReference(x)], distinct=true
 *   - `UPPER(name)`           → name='UPPER', arguments=[ColumnReference(name)]
 *   - `RANDOM()`              → name='RANDOM', arguments=[]
 *
 * Имя функции сохраняется как есть (регистр исходного SQL). Значение `*` в
 * аргументах представлено узлом {@see Star}.
 *
 * @property-read list<Expression> $arguments
 */
final class FunctionCall extends Expression
{
    /**
     * @param list<Expression> $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
        public readonly bool $distinct = false,
    ) {
        parent::__construct();
    }
}