<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Унарная операция над выражением.
 *
 * Поддерживаемые операторы на этапе 4:
 *   - `NOT <expr>`  (логическое отрицание)
 *   - `- <expr>`    (арифметическое отрицание)
 *   - `+ <expr>`    (унарный плюс)
 *   - `~ <expr>`    (побитовое НЕ)
 *
 * $operator хранится в верхнем регистре (`NOT`, `-`, `+`, `~`).
 */
final class UnaryOperation extends Expression
{
    public function __construct(
        public readonly string $operator,
        public readonly Expression $operand,
    ) {
        parent::__construct();
    }
}