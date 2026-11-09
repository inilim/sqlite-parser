<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Литеральное значение: целое, вещественное, строка или NULL.
 *
 * Поле $kind принимает одно из значений: 'int', 'float', 'string', 'null'.
 */
final class Literal extends Expression
{
    public function __construct(
        public readonly mixed $value,
        public readonly string $kind,
    ) {
        parent::__construct();
    }
}