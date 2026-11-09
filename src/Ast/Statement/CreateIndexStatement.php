<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция CREATE [UNIQUE] INDEX.
 *
 *   CREATE [UNIQUE] INDEX [IF NOT EXISTS] name ON tbl (columns) [WHERE expr]
 *
 * @property-read list<string> $columns
 */
final class CreateIndexStatement extends Statement
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly array $columns = [],
        public readonly bool $unique = false,
        public readonly bool $ifNotExists = false,
        public readonly ?Expression $where = null,
    ) {
        parent::__construct();
    }
}