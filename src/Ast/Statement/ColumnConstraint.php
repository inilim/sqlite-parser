<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Ограничение на уровне колонки в CREATE TABLE.
 *
 * Типы ($type):
 *   - `PRIMARY_KEY`  — PRIMARY KEY [ASC|DESC] [ON CONFLICT ...] [AUTOINCREMENT]
 *   - `NOT_NULL`     — NOT NULL [ON CONFLICT ...]
 *   - `UNIQUE`       — UNIQUE [ON CONFLICT ...]
 *   - `CHECK`        — CHECK (expr)
 *   - `DEFAULT`      — DEFAULT (expr) | DEFAULT literal
 *   - `COLLATE`      — COLLATE name
 *   - `REFERENCES`   — REFERENCES foreign_table (...) [ON DELETE/UPDATE ...]
 */
final class ColumnConstraint extends Node
{
    public function __construct(
        public readonly string $type,
        public readonly ?Expression $expression = null,
        public readonly ?string $value = null,
        public readonly ?ForeignKeyClause $foreignKey = null,
        public readonly ?string $conflictAction = null,
        public readonly bool $autoincrement = false,
        public readonly ?bool $asc = null,
    ) {
        parent::__construct();
    }
}