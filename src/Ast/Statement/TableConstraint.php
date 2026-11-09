<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Ограничение на уровне таблицы в CREATE TABLE.
 *
 * Типы ($type):
 *   - `PRIMARY_KEY`  — PRIMARY KEY (cols) [ON CONFLICT ...]
 *   - `UNIQUE`       — UNIQUE (cols) [ON CONFLICT ...]
 *   - `CHECK`        — CHECK (expr)
 *   - `FOREIGN_KEY`  — FOREIGN KEY (cols) REFERENCES ...
 *
 * $name — имя ограничения после CONSTRAINT name (если задано).
 *
 * @property-read list<string>|null $columns
 */
final class TableConstraint extends Node
{
    /**
     * @param list<string>|null $columns
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly ?array $columns = null,
        public readonly ?Expression $expression = null,
        public readonly ?ForeignKeyClause $foreignKey = null,
        public readonly ?string $conflictAction = null,
    ) {
        parent::__construct();
    }
}