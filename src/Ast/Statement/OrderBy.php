<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * ORDER BY item: выражение и необязательный флаг DESC.
 *
 * Пример: ORDER BY обновлен_unix DESC -> expression = ColumnReference('обновлен_unix'), desc = true
 */
final class OrderBy extends Node
{
    public function __construct(
        public ?Expression $expression = null,
        public bool $desc = false,
    ) {}

    public function getExpression(): ?Expression
    {
        return $this->expression;
    }

    public function getDesc(): bool
    {
        return $this->desc;
    }

    public function isDesc(): bool
    {
        return $this->desc;
    }

    public function isAsc(): bool
    {
        return !$this->desc;
    }
}