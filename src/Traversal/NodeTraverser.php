<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;

/**
 * Обходчик дерева AST с поддержкой нескольких посетителей.
 *
 * Порядок обхода: pre-order (enterNode) → дети → post-order (leaveNode).
 *
 * Пример:
 *
 *     $traverser = new NodeTraverser();
 *     $traverser->addVisitor(new MyVisitor());
 *     $traverser->traverse($script);
 */
final class NodeTraverser
{
    /** @var list<NodeVisitor> */
    private array $visitors = [];

    public function addVisitor(NodeVisitor $visitor): void
    {
        $this->visitors[] = $visitor;
    }

    /**
     * @return list<NodeVisitor>
     */
    public function getVisitors(): array
    {
        return $this->visitors;
    }

    public function traverse(Node $root): void
    {
        $this->walk($root);
    }

    private function walk(Node $node): void
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enterNode($node);
        }

        foreach (NodeChildExtractor::children($node) as $child) {
            $this->walk($child);
        }

        foreach ($this->visitors as $visitor) {
            $visitor->leaveNode($node);
        }
    }
}