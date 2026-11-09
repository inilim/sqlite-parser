<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Expression;

use Inilim\SqliteParser\Ast\Expression;

/**
 * Выражение CASE.
 *
 * Поддерживаются обе формы SQLite:
 *
 *   Searched CASE:
 *     CASE
 *         WHEN cond1 THEN res1
 *         WHEN cond2 THEN res2
 *         [ELSE resN]
 *     END
 *     → subject = null, whens = [WhenClause(cond1, res1), ...], else = resN|null
 *
 *   Simple CASE:
 *     CASE subject
 *         WHEN v1 THEN res1
 *         [ELSE resN]
 *     END
 *     → subject = Expression, whens = [WhenClause(v1, res1), ...], else = resN|null
 *
 * @property-read ?Expression      $subject
 * @property-read list<WhenClause> $whens
 * @property-read ?Expression      $else
 */
final class CaseExpression extends Expression
{
    /**
     * @param list<WhenClause> $whens
     */
    public function __construct(
        public readonly ?Expression $subject,
        public readonly array $whens,
        public readonly ?Expression $else,
    ) {
        parent::__construct();
    }
}