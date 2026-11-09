<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция управления транзакцией.
 *
 * Поддерживаемые формы:
 *
 *   BEGIN [DEFERRED|IMMEDIATE|EXCLUSIVE] [TRANSACTION [name]]
 *   COMMIT [TRANSACTION [name]]
 *   END TRANSACTION [name]
 *   ROLLBACK [TRANSACTION]
 *   ROLLBACK TO [SAVEPOINT] name
 *
 * Поля:
 *   - $type        — 'BEGIN', 'COMMIT', 'END' или 'ROLLBACK';
 *   - $mode        — 'DEFERRED', 'IMMEDIATE', 'EXCLUSIVE' (только для BEGIN);
 *   - $name        — имя транзакции / имя savepoint (для ROLLBACK TO);
 *   - $toSavepoint — true для `ROLLBACK TO [SAVEPOINT] name`.
 *
 * ВАЖНО: инструкция `END` без `TRANSACTION` не поддерживается — иначе
 * возникает неоднозначность с `TriggerBody` (`BEGIN ... END`) и
 * `CASE ... END`. Используйте `COMMIT` либо `END TRANSACTION`.
 */
final class TransactionStatement extends Statement
{
    public function __construct(
        public readonly string $type,
        public readonly string $mode = '',
        public readonly ?string $name = null,
        public readonly bool $toSavepoint = false,
    ) {
        parent::__construct();
    }
}