<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражение BETWEEN:
 *   `expr BETWEEN lower AND upper`     (not=false)
 *   `expr NOT BETWEEN lower AND upper` (not=true)
 */
final class BetweenExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly Expression $lower,
        public readonly Expression $upper,
        public readonly bool $not = false,
    ) {
        parent::__construct();
    }
}