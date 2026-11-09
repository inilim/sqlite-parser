<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обёртка над списком Assignment.
 *
 * Барьер против unwrap-флаттенинга phplrt — по аналогии с
 * {@see ResultColumnList} и {@see OrderByList}.
 *
 * @property-read list<Assignment> $items
 */
final class AssignmentList extends Node
{
    /**
     * @param list<Assignment> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
        parent::__construct();
    }
}