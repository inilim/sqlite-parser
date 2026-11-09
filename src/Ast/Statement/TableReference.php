<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Ссылка на таблицу или производную таблицу (derived table) в FROM-секции.
 *
 * Примеры:
 *   - `users`                       → name='users', alias=null
 *   - `users AS u`                  → name='users', alias='u'
 *   - `(SELECT ...) AS t`           → subquery=SelectStatement, alias='t'
 *   - `(SELECT ...) AS t (a, b)`    → subquery=SelectStatement, alias='t', columnAliases=['a','b']
 *
 * Одно из полей $name или $subquery должно быть задано.
 *
 * @property-read list<string>|null $columnAliases
 */
final class TableReference extends Node
{
    /**
     * @param list<string>|null $columnAliases
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $alias = null,
        public readonly ?Statement $subquery = null,
        public readonly ?array $columnAliases = null,
    ) {
        parent::__construct();
    }
}