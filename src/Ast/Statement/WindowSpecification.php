<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Спецификация окна в `OVER (...)`.
 *
 *   OVER (PARTITION BY a, b ORDER BY c ROWS BETWEEN ... AND ...)
 *
 * @property-read list<Expression>|null $partitionBy
 * @property-read list<OrderBy>|null    $orderBy
 */
final class WindowSpecification extends Node
{
    /**
     * @param list<Expression>|null $partitionBy
     * @param list<OrderBy>|null    $orderBy
     */
    public function __construct(
        public readonly ?array $partitionBy = null,
        public readonly ?array $orderBy = null,
        public readonly ?FrameSpec $frame = null,
    ) {
        parent::__construct();
    }
}