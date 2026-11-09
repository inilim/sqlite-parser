<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция EXPLAIN.
 *
 *   EXPLAIN statement
 *   EXPLAIN QUERY PLAN statement
 *
 * $queryPlan — true, если использован префикс `QUERY PLAN`.
 */
final class ExplainStatement extends Statement
{
    public function __construct(
        public readonly Statement $statement,
        public readonly bool $queryPlan = false,
    ) {
        parent::__construct();
    }
}