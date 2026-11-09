<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция CREATE [TEMP] VIEW.
 *
 *   CREATE [TEMP|TEMPORARY] VIEW [IF NOT EXISTS] name [(columns)] AS select
 *
 * @property-read list<string>|null $columns
 */
final class CreateViewStatement extends Statement
{
    /**
     * @param list<string>|null $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly Statement $select,
        public readonly bool $ifNotExists = false,
        public readonly bool $temporary = false,
        public readonly ?array $columns = null,
    ) {
        parent::__construct();
    }
}