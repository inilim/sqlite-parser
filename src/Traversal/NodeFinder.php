<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;

/**
 * Поиск узлов в дереве AST по классу либо произвольному условию.
 *
 * Примеры:
 *
 *     // Все ссылки на колонки:
 *     $finder = new NodeFinder(ColumnReference::class);
 *     $columns = $finder->findAll($script);
 *
 *     // Первая ссылка на колонку "id":
 *     $finder = new NodeFinder(
 *         static fn(Node $n): bool
 *             => $n instanceof ColumnReference && $n->name === 'id'
 *     );
 *     $idRef = $finder->findFirst($script);
 */
final class NodeFinder
{
    /** @var \Closure(Node): bool */
    private \Closure $predicate;

    /**
     * @param class-string<Node>|\Closure(Node): bool $predicate Класс узла либо замыкание-предикат.
     */
    public function __construct(string|\Closure $predicate)
    {
        if (is_string($predicate)) {
            $this->predicate = static fn(Node $n): bool => $n instanceof $predicate;
        } else {
            $this->predicate = $predicate;
        }
    }

    /**
     * @param class-string<Node> $class
     */
    public static function byClass(string $class): self
    {
        return new self($class);
    }

    /**
     * @param \Closure(Node): bool $predicate
     */
    public static function byPredicate(\Closure $predicate): self
    {
        return new self($predicate);
    }

    /**
     * @return list<Node>
     */
    public function findAll(Node $root): array
    {
        $found = [];
        $this->collect($root, $found);

        return $found;
    }

    public function findFirst(Node $root): ?Node
    {
        if (($this->predicate)($root)) {
            return $root;
        }
        foreach (NodeChildExtractor::children($root) as $child) {
            $result = $this->findFirst($child);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    public function contains(Node $root): bool
    {
        return $this->findFirst($root) !== null;
    }

    /**
     * @param list<Node> $found
     */
    private function collect(Node $node, array &$found): void
    {
        if (($this->predicate)($node)) {
            $found[] = $node;
        }
        foreach (NodeChildExtractor::children($node) as $child) {
            $this->collect($child, $found);
        }
    }
}
