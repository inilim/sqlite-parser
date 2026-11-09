<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

use JsonSerializable;

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
        $vars = get_object_vars($this);
        unset($vars['attributes']);

        /** @var array<string, mixed> $vars */
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