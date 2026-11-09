<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Секция RETURNING в DML-инструкциях.
 *
 *   INSERT ... RETURNING id, name
 *   UPDATE ... RETURNING *
 *   DELETE ... RETURNING id
 *
 * @property-read list<ResultColumn> $columns
 */
final class ReturningClause extends Node
{
    /**
     * @param list<ResultColumn> $columns
     */
    public function __construct(
        public readonly array $columns = [],
    ) {
        parent::__construct();
    }
}