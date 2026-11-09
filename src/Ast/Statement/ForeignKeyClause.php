<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Ссылка на внешнюю таблицу (для FOREIGN KEY в CREATE TABLE).
 *
 * @property-read list<string>|null $columns
 * @property-read list<string>|null $localColumns
 */
final class ForeignKeyClause extends Node
{
    /**
     * @param list<string>|null $columns
     * @param list<string>|null $localColumns
     */
    public function __construct(
        public readonly string $table,
        public readonly ?array $columns = null,
        public readonly ?array $localColumns = null,
        public readonly ?string $onDelete = null,
        public readonly ?string $onUpdate = null,
    ) {
        parent::__construct();
    }
}