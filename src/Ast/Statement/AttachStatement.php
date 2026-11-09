<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция ATTACH.
 *
 *   ATTACH [DATABASE] expr AS schema-name
 *
 * $expression — обычно строковый литерал (путь к файлу БД) либо выражение.
 */
final class AttachStatement extends Statement
{
    public function __construct(
        public readonly Expression $expression,
        public readonly string $schemaName,
    ) {
        parent::__construct();
    }
}