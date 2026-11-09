<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Секция HAVING в SELECT.
 *
 * Внутренний узел-обёртка, используется для того, чтобы в SelectCore можно
 * было однозначно отличить HAVING-выражение от WHERE-выражения — оба
 * возвращают Expression.
 */
final class HavingClause extends Node
{
    public function __construct(
        public readonly Expression $expression,
    ) {
        parent::__construct();
    }
}