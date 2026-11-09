<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция SAVEPOINT / RELEASE.
 *
 *   SAVEPOINT name
 *   RELEASE [SAVEPOINT] name
 *
 * $type — 'SAVEPOINT' или 'RELEASE'.
 */
final class SavepointStatement extends Statement
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
    ) {
        parent::__construct();
    }
}