<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Statement;

/**
 * Тело триггера `BEGIN ... END` — список инструкций.
 *
 * @property-read list<Statement> $statements
 */
final class TriggerBody extends Node
{
    /**
     * @param list<Statement> $statements
     */
    public function __construct(
        public readonly array $statements,
    ) {
        parent::__construct();
    }
}