<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Звёздочка `*` в списке выборки SELECT.
 *
 * На этапе 3 используется только в `SELECT * FROM ...` и внутри агрегатных
 * функций (например, `COUNT(*)`). На этапе 4 может расшириться информацией
 * о таблице (`t.*`).
 */
final class Star extends Expression
{
}