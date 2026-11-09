<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция CREATE TRIGGER.
 *
 *   CREATE [TEMP] TRIGGER [IF NOT EXISTS] name
 *       [BEFORE|AFTER|INSTEAD OF]
 *       { INSERT | UPDATE [OF cols] | DELETE }
 *       ON table
 *       [FOR EACH ROW]
 *       [WHEN expr]
 *       BEGIN statements END
 *
 * @property-read list<string>    $updateOf
 * @property-read list<Statement> $statements
 */
final class CreateTriggerStatement extends Statement
{
    /**
     * @param list<string>    $updateOf
     * @param list<Statement> $statements
     */
    public function __construct(
        public readonly string $name,
        public readonly string $event,
        public readonly string $table,
        public readonly string $timing = 'BEFORE',
        public readonly array $updateOf = [],
        public readonly bool $forEachRow = false,
        public readonly ?Expression $when = null,
        public readonly array $statements = [],
        public readonly bool $ifNotExists = false,
        public readonly bool $temporary = false,
    ) {
        parent::__construct();
    }
}