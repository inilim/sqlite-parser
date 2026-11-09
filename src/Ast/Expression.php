<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Ast;

/**
 * Базовый абстрактный класс для всех выражений SQL
 * (Literal, ColumnReference, BinaryOperation, FunctionCall, ...).
 */
abstract class Expression extends Node
{
}