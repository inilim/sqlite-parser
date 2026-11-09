<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражение IN:
 *   `expr IN (a, b, c)`     (not=false)
 *   `expr NOT IN (a, b, c)` (not=true)
 *
 * На этапе 4 поддерживается только форма со списком выражений.
 * Форма с подзапросом (`IN (SELECT ...)`) появится на этапе 5.
 *
 * @property-read list<Expression> $list
 */
final class InExpression extends Expression
{
    /**
     * @param list<Expression> $list
     */
    public function __construct(
        public readonly Expression $expression,
        public readonly array $list,
        public readonly bool $not = false,
    ) {
        parent::__construct();
    }
}