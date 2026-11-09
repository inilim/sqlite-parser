<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Действие внешнего ключа: `ON DELETE CASCADE`, `ON UPDATE SET NULL` и т.п.
 *
 * $type   — 'DELETE' или 'UPDATE'.
 * $action — 'CASCADE', 'RESTRICT', 'SET_NULL', 'SET_DEFAULT', 'NO_ACTION'.
 */
final class ForeignKeyAction extends Node
{
    public function __construct(
        public readonly string $type,
        public readonly string $action,
    ) {
        parent::__construct();
    }
}