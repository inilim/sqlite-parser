<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Printer;

use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Traversal\NodeChildExtractor;
use ReflectionObject;
use ReflectionProperty;

/**
 * Печатает структуру AST в читаемом виде (для отладки).
 *
 * Пример вывода:
 *
 *     Script
 *       statements[0]:
 *         SelectStatement [distinct=false]
 *           columns: ResultColumnList
 *             items[0]: ResultColumn [alias=null]
 *               expression: ColumnReference [name="id", table=null]
 *           from: TableReference [name="users", alias=null]
 *
 * Скалярные свойства узла печатаются в квадратных скобках прямо после
 * имени класса. Дочерние узлы и массивы узлов печатаются с отступом.
 */
final class NodeDumper
{
    public function dump(Node $root): string
    {
        $out = '';
        $this->dumpNode($root, 0, $out);

        return $out;
    }

    private function dumpNode(Node $node, int $depth, string &$out): void
    {
        $indent = str_repeat('  ', $depth);
        $ref = new ReflectionObject($node);
        $short = $ref->getShortName();

        $scalars = [];
        $children = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($node)) {
                continue;
            }
            $name = $prop->getName();
            $value = $prop->getValue($node);

            if ($value instanceof Node) {
                $children[$name] = $value;
            } elseif (is_array($value) && NodeChildExtractor::hasNodes($value)) {
                $children[$name] = $value;
            } else {
                $scalars[$name] = $value;
            }
        }

        $scalarStr = '';
        if ($scalars !== []) {
            $parts = [];
            foreach ($scalars as $k => $v) {
                $parts[] = $k . '=' . $this->scalarToString($v);
            }
            $scalarStr = ' [' . implode(', ', $parts) . ']';
        }

        $out .= $indent . $short . $scalarStr . "\n";

        foreach ($children as $name => $value) {
            if ($value instanceof Node) {
                $out .= $indent . '  ' . $name . ":\n";
                $this->dumpNode($value, $depth + 2, $out);
            } elseif (is_array($value)) {
                $this->dumpArray($value, $name, $depth, $out);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $arr
     */
    private function dumpArray(array $arr, string $prefix, int $depth, string &$out): void
    {
        $indent = str_repeat('  ', $depth);

        foreach ($arr as $i => $item) {
            if ($item instanceof Node) {
                $out .= $indent . '  ' . $prefix . '[' . $i . "]:\n";
                $this->dumpNode($item, $depth + 2, $out);
            } elseif (is_array($item) && NodeChildExtractor::hasNodes($item)) {
                $out .= $indent . '  ' . $prefix . '[' . $i . "]:\n";
                $this->dumpArray($item, $prefix, $depth + 2, $out);
            }
        }
    }

    private function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return '"' . addcslashes($value, "\"\\\n\r\t") . '"';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = $k . ':' . $this->scalarToString($v);
            }

            return '[' . implode(', ', $parts) . ']';
        }

        return gettype($value);
    }
}