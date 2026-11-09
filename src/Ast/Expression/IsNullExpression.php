<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражение IS [NOT] NULL:
 *
 *   expr IS NULL      (not=false)
 *   expr IS NOT NULL  (not=true)
 */
final class IsNullExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly bool $not = false,
    ) {
        parent::__construct();
    }
}