<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;
use ReflectionObject;
use ReflectionProperty;

/**
 * Извлекает прямых детей-узлы заданного узла AST.
 *
 * Все узлы проекта хранят свойства через конструктор как public readonly.
 * Для детей подходят любые значения типа Node, а также массивы, содержащие
 * Node (в том числе вложенные массивы — например, `INSERT ... VALUES ((...), (...))`).
 *
 * Скалярные свойства (string, int, bool, ?int, ассоциативные массивы
 * скаляров) — детьми не считаются и в обходе не участвуют.
 */
final class NodeChildExtractor
{
    /**
     * Возвращает список прямых дочерних узлов.
     *
     * @return list<Node>
     */
    public static function children(Node $node): array
    {
        $result = [];

        $ref = new ReflectionObject($node);
        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($node)) {
                continue;
            }
            $value = $prop->getValue($node);
            self::collect($value, $result);
        }

        return $result;
    }

    /**
     * Проверяет, содержит ли значение хотя бы один Node (на любой глубине).
     */
    public static function hasNodes(mixed $value): bool
    {
        if ($value instanceof Node) {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasNodes($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<Node> $out
     */
    private static function collect(mixed $value, array &$out): void
    {
        if ($value instanceof Node) {
            $out[] = $value;
            return;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::collect($item, $out);
            }
        }
    }
}