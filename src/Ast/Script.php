<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

/**
 * Корневой узел скрипта — последовательность инструкций SQL, разделённых `;`.
 *
 * Одиночная инструкция получается через $script->statements[0].
 */
final class Script extends Node
{
    /**
     * @param list<Statement> $statements
     */
    public function __construct(
        public readonly array $statements
    ) {
        parent::__construct();
    }
}