<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Опции таблицы в CREATE TABLE: `WITHOUT ROWID` и/или `STRICT`.
 */
final class TableOptions extends Node
{
    public function __construct(
        public readonly bool $withoutRowid = false,
        public readonly bool $strict = false,
    ) {
        parent::__construct();
    }
}