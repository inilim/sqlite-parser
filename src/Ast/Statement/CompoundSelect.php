<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Составной SELECT-запрос (`UNION [ALL]`, `INTERSECT`, `EXCEPT`).
 *
 * Представляет последовательность из двух и более {@see SelectStatement},
 * объединённых операторами. Поле $operators содержит строки в верхнем
 * регистре: `UNION`, `UNION ALL`, `INTERSECT`, `EXCEPT`.
 *
 * @property-read list<SelectStatement> $selects
 * @property-read list<string>          $operators
 */
final class CompoundSelect extends Statement
{
    /**
     * @param list<SelectStatement> $selects
     * @param list<string>          $operators
     * @param list<OrderBy>|null    $orderBy
     * @param array{limit:int,offset:?int}|null $limit
     */
    public function __construct(
        public readonly array $selects,
        public readonly array $operators,
        public readonly ?WithClause $with = null,
        public readonly ?array $orderBy = null,
        public readonly ?array $limit = null,
    ) {
        parent::__construct();
    }
}