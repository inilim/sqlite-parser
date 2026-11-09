<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция ALTER TABLE.
 *
 * Действия ($action):
 *   - `RENAME_TO`     — RENAME TO new_name
 *   - `RENAME_COLUMN` — RENAME [COLUMN] old TO new
 *   - `ADD_COLUMN`    — ADD [COLUMN] column_def
 *   - `DROP_COLUMN`   — DROP [COLUMN] column_name
 */
final class AlterTableStatement extends Statement
{
    public function __construct(
        public readonly string $table,
        public readonly string $action,
        public readonly ?string $oldName = null,
        public readonly ?string $newName = null,
        public readonly ?ColumnDefinition $column = null,
    ) {
        parent::__construct();
    }
}