<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Подзапрос как выражение: `(SELECT ...)`.
 *
 * Например:
 *   - скалярный подзапрос:    `SELECT (SELECT MAX(x) FROM t)`
 *   - подзапрос в FROM:       `SELECT * FROM (SELECT ...) AS t`
 *   - подзапрос в IN:         `a IN (SELECT id FROM t)`
 */
final class Subquery extends Expression
{
    public function __construct(
        public readonly Statement $query,
    ) {
        parent::__construct();
    }
}