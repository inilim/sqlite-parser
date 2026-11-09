<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;
use ReflectionClass;

/**
 * Утилита создания копии узла с заменой отдельных свойств.
 *
 * Работает за счёт того, что каждый узел AST принимает все свои публичные
 * свойства через конструктор, а имена параметров совпадают с именами
 * свойств. Это позволяет пересобрать узел, не прибегая к ReflectionProperty::setValue
 * (который в PHP 8.4 не может изменять readonly-свойства после инициализации).
 *
 * Пример:
 *
 *     $newRef = NodeCloner::with($oldRef, ['name' => 'user_id']);
 */
final class NodeCloner
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function with(Node $node, array $overrides): Node
    {
        $ref = new ReflectionClass($node);
        $ctor = $ref->getConstructor();

        if ($ctor === null) {
            throw new \RuntimeException('Node has no constructor: ' . $ref->getName());
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $name = $param->getName();
            $args[] = array_key_exists($name, $overrides)
                ? $overrides[$name]
                : $node->$name;
        }

        return $ref->newInstanceArgs($args);
    }
}