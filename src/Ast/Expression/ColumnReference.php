<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Ссылка на колонку по имени.
 *
 * На этапе 3 поддерживается только простое имя (`id`, `"user name"`).
 * Квалификация через точку (`t.id`) появится на этапе 4.
 */
final class ColumnReference extends Expression
{
    public function __construct(
        public readonly string $name,
    ) {
        parent::__construct();
    }
}