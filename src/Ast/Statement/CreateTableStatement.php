<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция CREATE TABLE.
 *
 * Поддерживает формы:
 *
 *   CREATE [TEMP|TEMPORARY] TABLE [IF NOT EXISTS] name (
 *       column_def [, column_def]*
 *       [, table_constraint]*
 *   ) [WITHOUT ROWID] [, STRICT]
 *
 *   CREATE TABLE name AS SELECT ...
 *
 * @property-read list<ColumnDefinition> $columns
 * @property-read list<TableConstraint>  $tableConstraints
 */
final class CreateTableStatement extends Statement
{
    /**
     * @param list<ColumnDefinition> $columns
     * @param list<TableConstraint>  $tableConstraints
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns = [],
        public readonly array $tableConstraints = [],
        public readonly bool $ifNotExists = false,
        public readonly bool $temporary = false,
        public readonly bool $withoutRowid = false,
        public readonly bool $strict = false,
        public readonly ?Statement $asSelect = null,
    ) {
        parent::__construct();
    }
}