<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Бинарная операция над двумя выражениями.
 *
 * На этапе 3 — только операции сравнения (`=`, `<>`, `!=`, `<`, `<=`, `>`, `>=`).
 * На этапе 4 расширится арифметикой, конкатенацией и логическими операторами.
 *
 * $operator хранится в исходной форме SQL (например, `=`, `<>`, `<=`).
 */
final class BinaryOperation extends Expression
{
    public function __construct(
        public readonly string $operator,
        public readonly Expression $left,
        public readonly Expression $right,
    ) {
        parent::__construct();
    }
}