<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция SELECT.
 *
 * Поддерживает:
 *   WITH [RECURSIVE] cte...    — опционально,
 *   SELECT [DISTINCT|ALL] cols — список выборки,
 *   FROM tbl [JOIN ...]        — базовая таблица + JOIN-ы,
 *   WHERE expr,
 *   GROUP BY expr, ...
 *   HAVING expr,
 *   ORDER BY ..., LIMIT ... .
 *
 * @property-read list<ResultColumn>            $columns
 * @property-read bool                          $distinct
 * @property-read ?TableReference               $from
 * @property-read list<JoinClause>              $joins
 * @property-read ?Expression                   $where
 * @property-read ?GroupByClause                $groupBy
 * @property-read ?Expression                   $having
 * @property-read ?WithClause                   $with
 * @property-read ?list<OrderBy>                $orderBy
 * @property-read ?array{limit:int,offset:?int} $limit
 */
final class SelectStatement extends Statement
{
    /**
     * @param list<ResultColumn> $columns
     * @param list<JoinClause>   $joins
     * @param list<OrderBy>|null $orderBy
     * @param array{limit:int,offset:?int}|null $limit
     */
    public function __construct(
        public readonly array $columns,
        public readonly bool $distinct = false,
        public readonly ?TableReference $from = null,
        public readonly array $joins = [],
        public readonly ?Expression $where = null,
        public readonly ?GroupByClause $groupBy = null,
        public readonly ?Expression $having = null,
        public readonly ?WithClause $with = null,
        public readonly ?array $orderBy = null,
        public readonly ?array $limit = null,
    ) {
        parent::__construct();
    }
}