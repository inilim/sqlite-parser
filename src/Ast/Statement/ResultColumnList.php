<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обёртка над списком ResultColumn в SELECT.
 *
 * Зачем нужна: phplrt при unwrap конкатенации в родительском правиле
 * "схлопывает" вложенные массивы — если подправило вернуло голый `array`,
 * он может быть развёрнут в плоский `$children` родителя, что приводит к
 * потере элементов (см. SKILL.md → «gotcha», PHPLRT_GUIDE.md → §3.4).
 *
 * Возвращая объект `ResultColumnList` из `SelectList`, мы гарантируем, что
 * `SelectStatement` получит его как единый элемент `$children[0]`, а не как
 * развёрнутый список RC.
 *
 * @property-read list<ResultColumn> $items
 */
final class ResultColumnList extends Node
{
    /**
     * @param list<ResultColumn> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
        parent::__construct();
    }
}