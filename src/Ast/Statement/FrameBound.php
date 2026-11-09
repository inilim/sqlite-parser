<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Одна граница рамки окна.
 *
 * Поле $type — одно из:
 *   - `UNBOUNDED_PRECEDING`
 *   - `UNBOUNDED_FOLLOWING`
 *   - `CURRENT_ROW`
 *   - `EXPR_PRECEDING`
 *   - `EXPR_FOLLOWING`
 *
 * Для `EXPR_*` поле $expression содержит выражение смещения.
 */
final class FrameBound extends Node
{
    public function __construct(
        public readonly string $type,
        public readonly ?Expression $expression = null,
    ) {
        parent::__construct();
    }
}