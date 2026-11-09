<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция UPDATE.
 *
 * Поддерживает формы:
 *
 *   UPDATE [OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK] tbl
 *       SET col = expr, ...
 *       [WHERE expr]
 *       [RETURNING ...]
 *
 * @property-read list<Assignment> $assignments
 * @property-read ?Expression      $where
 * @property-read ?string          $orAction
 * @property-read ?ReturningClause $returning
 */
final class UpdateStatement extends Statement
{
    /**
     * @param list<Assignment> $assignments
     */
    public function __construct(
        public readonly string $table,
        public readonly array $assignments = [],
        public readonly ?Expression $where = null,
        public readonly ?string $orAction = null,
        public readonly ?ReturningClause $returning = null,
    ) {
        parent::__construct();
    }
}