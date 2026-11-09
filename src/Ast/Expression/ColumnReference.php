<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Ссылка на колонку по имени.
 *
 * Поддерживаются:
 *   - простое имя:          `id`, `"user name"`   → name='id', table=null
 *   - квалифицированное:    `t.id`, `"t"."id"`    → name='id', table='t'
 *
 * `table` хранится без кавычек (если использовались обрамляющие кавычки — они
 * снимаются на этапе редукции токена T_QUOTED_IDENT).
 */
final class ColumnReference extends Expression
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $table = null,
    ) {
        parent::__construct();
    }
}