<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * FROM-секция SELECT: базовый источник + список JOIN-ов.
 *
 * Внутренний узел. В {@see SelectStatement} раскладывается на поля
 * `from` (базовая таблица) и `joins` (список {@see JoinClause}).
 *
 * @property-read list<JoinClause> $joins
 */
final class FromClause extends Node
{
    /**
     * @param list<JoinClause> $joins
     */
    public function __construct(
        public readonly TableReference $base,
        public readonly array $joins = [],
    ) {
        parent::__construct();
    }
}