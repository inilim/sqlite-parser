<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;

/**
 * Базовый абстрактный узел AST.
 *
 * Все конкретные узлы (Script, Statement, Expression и их наследники) содержат
 * readonly-свойства и вызывают parent::__construct() в конце своего конструктора.
 *
 * ВАЖНО (фикс утечки памяти / экспоненциального дублирования):
 *
 * Раньше Node хранил публичное свойство `attributes`, в которое в конструкторе
 * записывался снимок ВСЕХ публичных свойств узла, включая дочерние узлы AST
 * (Expression / Statement / Node). Это приводило к тому, что каждый узел хранил
 * ссылки на всех своих потомков ещё и через `attributes`, помимо собственно
 * свойств. При рекурсивном обходе (print_r, var_export, json_encode,
 * любой дампер, рекурсивный сравнением) поддерево дублировалось на каждом
 * уровне вложенности, то есть объём обхода рос как O(2^depth).
 *
 * На сколько-нибудь сложном запросе (CTE + JOIN + подзапросы) `print_r($script)`
 * пытался выделить сотни мегабайт в одном `print_r` — что и приводило к
 * "Allowed memory size ... exhausted" на строке 20 в test.php.
 *
 * Сейчас `attributes` удалено; сериализация выполняется лениво через
 * jsonSerialize(), который обходит свойства ровно один раз (без дублирования).
 * Свойство `attributes` больше не существует — используйте json_encode($node)
 * либо рефлексию по конкретным полям.
 *
 * Если в вашем коде где-то использовалось `$node->attributes` — замените на
 * `$node->jsonSerialize()` (или `json_encode($node)` для полного дерева).
 */
abstract class Node implements JsonSerializable
{
    public function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $reflection = new ReflectionObject($this);

        /** @var array<string, mixed> $vars */
        $vars = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isInitialized($this)) {
                continue;
            }

            $vars[$property->getName()] = $property->getValue($this);
        }

        return $vars;
    }
}