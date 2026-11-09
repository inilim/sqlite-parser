<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Спецификация рамки окна: `ROWS <start> [AND <end>]` или
 * `RANGE <start> [AND <end>]`.
 *
 * Поле $unit — `ROWS` или `RANGE`.
 */
final class FrameSpec extends Node
{
    public function __construct(
        public readonly string $unit,
        public readonly FrameBound $start,
        public readonly ?FrameBound $end = null,
    ) {
        parent::__construct();
    }
}