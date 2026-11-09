<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Секция WHERE в SELECT.
 *
 * Внутренний узел-обёртка, используется для того, чтобы в SelectCore можно
 * было однозначно отличить WHERE-выражение от HAVING-выражения — оба
 * возвращают Expression, и без обёртки при отсутствии WHERE выражение
 * HAVING ошибочно попадало в поле where.
 */
final class WhereClause extends Node
{
    public function __construct(
        public readonly Expression $expression,
    ) {
        parent::__construct();
    }
}