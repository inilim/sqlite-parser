<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражения LIKE / GLOB:
 *
 *   expr LIKE pattern      (not=false, glob=false)
 *   expr NOT LIKE pattern  (not=true,  glob=false)
 *   expr GLOB pattern      (not=false, glob=true)
 *   expr NOT GLOB pattern  (not=true,  glob=true)
 */
final class LikeExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly Expression $pattern,
        public readonly bool $not = false,
        public readonly bool $glob = false,
    ) {
        parent::__construct();
    }
}