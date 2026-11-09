<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Секция WITH в SELECT (`WITH [RECURSIVE] name AS (...), ...`).
 *
 * @property-read list<CommonTableExpression> $ctes
 */
final class WithClause extends Node
{
    /**
     * @param list<CommonTableExpression> $ctes
     */
    public function __construct(
        public readonly bool $recursive = false,
        public readonly array $ctes = [],
    ) {
        parent::__construct();
    }
}