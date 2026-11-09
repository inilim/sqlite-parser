<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Определение колонки в CREATE TABLE.
 *
 * Пример: `id INTEGER PRIMARY KEY AUTOINCREMENT`.
 *
 * @property-read list<ColumnConstraint> $constraints
 */
final class ColumnDefinition extends Node
{
    /**
     * @param list<ColumnConstraint> $constraints
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $type = null,
        public readonly array $constraints = [],
    ) {
        parent::__construct();
    }
}