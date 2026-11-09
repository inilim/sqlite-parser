<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция INSERT.
 *
 * Поддерживает формы:
 *
 *   INSERT [OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK] INTO tbl [(cols)]
 *       VALUES (...), (...)
 *       | SELECT ... | DEFAULT VALUES
 *       [ON CONFLICT (...) DO UPDATE SET ... | DO NOTHING]
 *       [RETURNING ...]
 *
 * Одно из полей $rows / $select / $defaultValues === true будет задано.
 *
 * @property-read list<string>|null         $columns
 * @property-read list<list<Expression>>|null $rows
 * @property-read ?Statement                $select
 * @property-read bool                      $defaultValues
 * @property-read ?string                   $orAction
 * @property-read ?UpsertClause             $upsert
 * @property-read ?ReturningClause          $returning
 */
final class InsertStatement extends Statement
{
    /**
     * @param list<string>|null             $columns
     * @param list<list<Expression>>|null   $rows
     */
    public function __construct(
        public readonly string $table,
        public readonly ?array $columns = null,
        public readonly ?array $rows = null,
        public readonly ?Statement $select = null,
        public readonly bool $defaultValues = false,
        public readonly ?string $orAction = null,
        public readonly ?UpsertClause $upsert = null,
        public readonly ?ReturningClause $returning = null,
    ) {
        parent::__construct();
    }
}