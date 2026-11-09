<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Node;

/**
 * Один элемент секции ORDER BY.
 *
 * На этапе 3 direction всегда 'ASC' — поддержка явного ASC/DESC будет
 * добавлена на этапе 5 (продвинутый SELECT).
 */
final class OrderBy extends Node
{
    public function __construct(
        public readonly Expression $expression,
        public readonly string $direction = 'ASC',
    ) {
        parent::__construct();
    }
}