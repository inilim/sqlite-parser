<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Одно CTE: `name [(col1, col2)] AS (subquery)`.
 *
 * @property-read list<string>|null $columns
 */
final class CommonTableExpression extends Node
{
    /**
     * @param list<string>|null $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly ?array $columns,
        public readonly Statement $query,
    ) {
        parent::__construct();
    }
}