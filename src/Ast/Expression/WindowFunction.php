<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement\WindowSpecification;

/**
 * Вызов функции с оконным модификатором `OVER`.
 *
 *   - `SUM(x) OVER (PARTITION BY g)` — $specification задана
 *   - `SUM(x) OVER w`               — $windowName задан
 */
final class WindowFunction extends Expression
{
    public function __construct(
        public readonly FunctionCall $function,
        public readonly ?WindowSpecification $specification = null,
        public readonly ?string $windowName = null,
    ) {
        parent::__construct();
    }
}