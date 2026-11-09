<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Statement;

/**
 * Инструкция DETACH.
 *
 *   DETACH [DATABASE] schema-name
 */
final class DetachStatement extends Statement
{
    public function __construct(
        public readonly string $schemaName,
    ) {
        parent::__construct();
    }
}