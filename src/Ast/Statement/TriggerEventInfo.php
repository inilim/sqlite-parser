<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Информация о событии триггера (INSERT / UPDATE [OF ...] / DELETE).
 *
 * $event    — 'INSERT', 'UPDATE' или 'DELETE'.
 * $updateOf — список колонок для `UPDATE OF col1, col2, ...`.
 *
 * Барьер против unwrap-флаттенинга: если бы правило возвращало голый
 * ассоциативный массив, его значения могли бы «развернуться» в $children
 * родителя (см. PHPLRT_GUIDE.md §3.4).
 *
 * @property-read list<string> $updateOf
 */
final class TriggerEventInfo extends Node
{
    /**
     * @param list<string> $updateOf
     */
    public function __construct(
        public readonly string $event,
        public readonly array $updateOf = [],
    ) {
        parent::__construct();
    }
}