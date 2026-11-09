<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Ограничение JOIN (`ON expr` или `USING (col, ...)`).
 *
 * Вспомогательный узел-контейнер, используется только внутри
 * {@see JoinClause} — чтобы не разворачиваться unwrap-флаттенингом в
 * `$children` родителя.
 *
 * @property-read list<string>|null $using
 */
final class JoinConstraint extends Node
{
    /**
     * @param list<string>|null $using
     */
    public function __construct(
        public readonly ?Expression $on = null,
        public readonly ?array $using = null,
    ) {
        parent::__construct();
    }
}