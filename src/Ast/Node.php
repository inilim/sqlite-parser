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
 * После инициализации $attributes хранит снимок всех публичных свойств узла —
 * это используется для отладки и snapshot-тестов (jsonSerialize()).
 */
abstract class Node implements JsonSerializable
{
    /**
     * Снимок публичных свойств узла на момент завершения конструктора.
     *
     * @var array<string, mixed>
     */
    public readonly array $attributes;

    public function __construct()
    {
        $reflection = new ReflectionObject($this);

        /** @var array<string, mixed> $vars */
        $vars = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if ($name === 'attributes') {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $vars[$name] = $property->getValue($this);
        }

        $this->attributes = $vars;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}