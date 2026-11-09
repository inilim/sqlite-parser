<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Одно присваивание в `SET col = expr`.
 *
 * Используется в:
 *   - UPDATE ... SET ...,
 *   - INSERT ... ON CONFLICT DO UPDATE SET ...
 */
final class Assignment extends Node
{
    public function __construct(
        public readonly string $column,
        public readonly ?Expression $value = null,
    ) {
        parent::__construct();
    }
}