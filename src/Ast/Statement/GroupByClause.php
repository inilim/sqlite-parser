<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Секция GROUP BY в SELECT.
 *
 * @property-read list<Expression> $expressions
 */
final class GroupByClause extends Node
{
    /**
     * @param list<Expression> $expressions
     */
    public function __construct(
        public readonly array $expressions = [],
    ) {
        parent::__construct();
    }
}