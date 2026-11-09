<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast\Statement;

use Inilim\SqliteParser\Ast\Node;

/**
 * Вспомогательный узел-маркер: тело `DEFAULT VALUES` в INSERT.
 */
final class InsertDefaultBody extends Node
{
}