<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Вспомогательный узел: тело `VALUES (...), (...), ...` в INSERT.
 *
 * Обёртка защищает phplrt от unwrap-флаттенинга: если бы reducer
 * возвращал голый `array<list<Expression>>`, он мог бы быть развёрнут в
 * `$children` родителя (см. PHPLRT_GUIDE.md §3.4).
 *
 * @property-read list<list<\Inilim\SqliteParser\Ast\Expression>> $rows
 */
final class InsertValuesBody extends Node
{
    /**
     * @param list<list<\Inilim\SqliteParser\Ast\Expression>> $rows
     */
    public function __construct(
        public readonly array $rows,
    ) {
        parent::__construct();
    }
}