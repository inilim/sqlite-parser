<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция DELETE.
 *
 * Поддерживает формы:
 *
 *   DELETE FROM tbl [WHERE expr] [RETURNING ...]
 */
final class DeleteStatement extends Statement
{
    public function __construct(
        public readonly string $table,
        public readonly ?Expression $where = null,
        public readonly ?ReturningClause $returning = null,
    ) {
        parent::__construct();
    }
}