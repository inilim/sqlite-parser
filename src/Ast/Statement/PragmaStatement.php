<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция PRAGMA.
 *
 *   PRAGMA name
 *   PRAGMA schema.name
 *   PRAGMA name = value
 *   PRAGMA schema.name = value
 *   PRAGMA name(value)
 *   PRAGMA schema.name(value)
 *
 * $value — Expression (обычно {@see \Inilim\SqliteParser\Ast\Expression\Literal})
 * или null, если значение не задано.
 */
final class PragmaStatement extends Statement
{
    public function __construct(
        public readonly string $name,
        public readonly ?Expression $value = null,
        public readonly ?string $schema = null,
    ) {
        parent::__construct();
    }
}