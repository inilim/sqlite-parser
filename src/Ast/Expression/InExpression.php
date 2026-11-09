<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Выражение IN:
 *   `expr IN (a, b, c)`      (not=false, subquery=null)
 *   `expr NOT IN (a, b, c)`  (not=true,  subquery=null)
 *   `expr IN (SELECT ...)`   (not=false, list=[], subquery=SelectStatement)
 *   `expr NOT IN (SELECT ...)` (not=true, list=[], subquery=SelectStatement)
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
        public readonly array $list = [],
        public readonly bool $not = false,
        public readonly ?Statement $subquery = null,
    ) {
        parent::__construct();
    }
}