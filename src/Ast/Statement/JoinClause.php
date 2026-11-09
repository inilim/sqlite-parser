<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Один JOIN-клауз в FROM-секции SELECT.
 *
 * Соответствует SQL-конструкции вида:
 *
 *   [NATURAL] [[INNER|LEFT [OUTER]|RIGHT [OUTER]|FULL [OUTER]|CROSS] JOIN] <table>
 *       [ON <expr> | USING (col1, col2, ...)]
 *
 * Поле $type — строка в верхнем регистре: `INNER`, `LEFT`, `RIGHT`, `FULL`,
 * `CROSS` или пустая строка для простого `JOIN`. Поле $natural — true для
 * `NATURAL ... JOIN`. Поля $on и $using взаимоисключающие (одно из них
 * задано) или оба null для CROSS JOIN.
 *
 * @property-read list<string>|null $using
 */
final class JoinClause extends Node
{
    /**
     * @param list<string>|null $using
     */
    public function __construct(
        public readonly string $type,
        public readonly TableReference $table,
        public readonly ?Expression $on = null,
        public readonly ?array $using = null,
        public readonly bool $natural = false,
    ) {
        parent::__construct();
    }
}