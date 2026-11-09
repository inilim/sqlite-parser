<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Секция UPSERT: `ON CONFLICT (...) DO UPDATE SET ... [WHERE ...]`
 * или `ON CONFLICT (...) DO NOTHING`.
 *
 * Поле $assignments === null означает DO NOTHING (нет SET).
 *
 * @property-read list<string>|null     $conflictTarget
 * @property-read list<Assignment>|null $assignments
 * @property-read ?Expression           $where
 */
final class UpsertClause extends Node
{
    /**
     * @param list<string>|null     $conflictTarget
     * @param list<Assignment>|null $assignments
     */
    public function __construct(
        public readonly ?array $conflictTarget = null,
        public readonly ?array $assignments = null,
        public readonly ?Expression $where = null,
    ) {
        parent::__construct();
    }
}