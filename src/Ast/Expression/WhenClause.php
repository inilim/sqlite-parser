<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Одна ветка WHEN ... THEN ... внутри {@see CaseExpression}.
 *
 * Поле $when — условие (в searched CASE) или сравниваемое значение
 * (в simple CASE). Поле $then — возвращаемое выражение.
 */
final class WhenClause extends Node
{
    public function __construct(
        public readonly Expression $when,
        public readonly Expression $then,
    ) {
        parent::__construct();
    }
}