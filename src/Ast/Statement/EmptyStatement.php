<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Пустая инструкция.
 *
 * Используется на этапе 2 как placeholder для `;` без содержимого.
 * На этапе 3 будет замещена конкретными реализациями Statement
 * (SelectStatement, InsertStatement, ...).
 */
final class EmptyStatement extends Statement
{
}