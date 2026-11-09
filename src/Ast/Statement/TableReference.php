<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Ссылка на таблицу в FROM-секции SELECT.
 *
 * Например:
 *   - `users`            → name='users', alias=null
 *   - `users AS u`       → name='users', alias='u'
 */
final class TableReference extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $alias = null,
    ) {
        parent::__construct();
    }
}