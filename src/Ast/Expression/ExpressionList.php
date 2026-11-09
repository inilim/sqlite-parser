<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обёртка над списком выражений через запятую.
 *
 * Используется в нескольких местах:
 *   - `IN (a, b, c)` — список сравниваемых значений,
 *   - `f(a, b, c)` — аргументы функции,
 *   - `(a, b) = (1, 2)` — tuple-выражения (на этапе 5+).
 *
 * Объект-контейнер защищает от unwrap-флаттенинга phplrt, при котором
 * массив разворачивается в плоский `$children` родительского правила
 * (см. {@see \Inilim\SqliteParser\Ast\Statement\ResultColumnList}).
 *
 * @property-read list<\Inilim\SqliteParser\Ast\Expression> $items
 */
final class ExpressionList extends Node
{
    /**
     * @param list<\Inilim\SqliteParser\Ast\Expression> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
        parent::__construct();
    }
}