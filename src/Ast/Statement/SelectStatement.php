<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция SELECT.
 *
 * На этапе 3 поддерживается минимальный набор:
 *   SELECT <columns> [FROM <table>] [WHERE <expr>] [ORDER BY ...] [LIMIT ...]
 *
 * @property-read list<ResultColumn>    $columns
 * @property-read ?TableReference       $from
 * @property-read ?Expression           $where
 * @property-read ?list<OrderBy>        $orderBy
 * @property-read ?array{limit:int,offset:?int} $limit
 */
final class SelectStatement extends Statement
{
    /**
     * @param list<ResultColumn> $columns
     * @param list<OrderBy>|null $orderBy
     * @param array{limit:int,offset:?int}|null $limit
     */
    public function __construct(
        public readonly array $columns,
        public readonly ?TableReference $from = null,
        public readonly ?Expression $where = null,
        public readonly ?array $orderBy = null,
        public readonly ?array $limit = null,
    ) {
        parent::__construct();
    }
}