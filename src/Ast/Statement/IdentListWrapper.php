<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обёртка над списком идентификаторов.
 *
 * Barrier-узел против unwrap-флаттенинга phplrt: если правило `IdentList`
 * возвращало бы голый `array<list<string>>`, он "схлопывался" бы в `$children`
 * родителя, и, например, `INSERT INTO t (a, b)` давал бы `'a'`, `'b'`
 * отдельными элементами — то есть колонки терялись бы. Аналогичная проблема
 * у `TableReference`/`JoinConstraint`/`CommonTableExpression`/`UpsertClause`.
 *
 * @property-read list<string> $items
 */
final class IdentListWrapper extends Node
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
        parent::__construct();
    }
}