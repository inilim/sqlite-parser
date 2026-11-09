<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обёртка над списком OrderBy в SELECT.
 *
 * См. пояснение в {@see ResultColumnList}: используется как «barrier» против
 * unwrap-флаттенинга phplrt, чтобы `SelectStatement` получил список
 * элементов одной ссылкой, а не развёрнутым массивом в `$children`.
 *
 * @property-read list<OrderBy> $items
 */
final class OrderByList extends Node
{
    /**
     * @param list<OrderBy> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
        parent::__construct();
    }
}