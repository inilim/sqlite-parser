<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;

/**
 * Базовый класс посетителя AST.
 *
 * Наследник переопределяет enterNode() и/или leaveNode() по необходимости.
 * `enterNode` вызывается до обхода детей (pre-order), `leaveNode` — после
 * (post-order).
 *
 * Пример: собрать все ColumnReference:
 *
 *     final class ColumnCollector extends NodeVisitor
 *     {
 *         public array $columns = [];
 *
 *         public function enterNode(Node $node): void
 *         {
 *             if ($node instanceof \Inilim\SqliteParser\Ast\Expression\ColumnReference) {
 *                 $this->columns[] = $node;
 *             }
 *         }
 *     }
 */
abstract class NodeVisitor
{
    public function enterNode(Node $node): void
    {
    }

    public function leaveNode(Node $node): void
    {
    }
}