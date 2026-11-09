<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция DROP {TABLE|INDEX|VIEW|TRIGGER} [IF EXISTS] name.
 *
 * $objectType — 'TABLE', 'INDEX', 'VIEW' или 'TRIGGER'.
 */
final class DropStatement extends Statement
{
    public function __construct(
        public readonly string $objectType,
        public readonly string $name,
        public readonly bool $ifExists = false,
    ) {
        parent::__construct();
    }
}