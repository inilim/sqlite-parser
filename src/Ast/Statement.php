<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

/**
 * Базовый абстрактный класс для всех инструкций SQL
 * (SelectStatement, InsertStatement, CreateTableStatement, ...).
 */
abstract class Statement extends Node
{
}