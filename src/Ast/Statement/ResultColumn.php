<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Элемент списка выборки SELECT.
 *
 * Например:
 *   - `id`                → expression=ColumnReference('id'), alias=null
 *   - `id AS user_id`     → expression=ColumnReference('id'), alias='user_id'
 *   - `1 + 2 AS sum`      → expression=BinaryOperation, alias='sum'
 *   - `*`                 → expression=Star(), alias=null
 */
final class ResultColumn extends Node
{
    public function __construct(
        public readonly Expression $expression,
        public readonly ?string $alias = null,
    ) {
        parent::__construct();
    }
}