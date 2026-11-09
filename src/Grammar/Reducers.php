<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Grammar;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Phplrt\Contracts\Lexer\TokenInterface;

/**
 * Утилиты для редукторов PP3-грамматики.
 *
 * phplrt возвращает результат повторения `(Rule() Op())*` в одной из нескольких
 * форм, которая зависит от количества итераций и внутреннего устройства парсера:
 *
 *   flat:        [op1, value1, op2, value2, ...]
 *   nested:      [[op1, value1], [op2, value2], ...]
 *   flat-inner:  [op, value]                 (ровно одна итерация)
 *   value-list:  [value1, value2, ...]        (когда оператор выброшен через ::T_X::)
 *
 * {@see self::normalizeRepeat()} рекурсивно «выпрямляет» все эти формы в
 * плоский массив, а {@see self::foldLeftBinaryChain()} сворачивает результат
 * в левоассоциативную цепочку BinaryOperation.
 *
 * Редуктор должен передавать в foldLeftBinaryChain **всё** содержимое
 * `$children` начиная с индекса 1 (через `\array_slice($children, 1)`), а не
 * только `$children[1]` — иначе при полностью «плоском» результате повтора
 * (например, `[left, op, value, op, value]`) теряется всё, кроме первого
 * элемента.
 */
final class Reducers
{
    /**
     * Рекурсивно «выпрямляет» произвольную форму результата повтора в
     * плоский массив листьев. Массивы разворачиваются на любую глубину,
     * скаляры и объекты (Tokens, Closures, AST-узлы) сохраняются как есть.
     *
     * @return list<mixed>
     */
    public static function normalizeRepeat(mixed $rest): array
    {
        if ($rest === null) {
            return [];
        }
        if (!\is_array($rest)) {
            return [$rest];
        }
        if ($rest === []) {
            return [];
        }

        $flat = [];
        foreach ($rest as $item) {
            if (\is_array($item)) {
                foreach (self::normalizeRepeat($item) as $sub) {
                    $flat[] = $sub;
                }
            } else {
                $flat[] = $item;
            }
        }

        return $flat;
    }

    /**
     * Сворачивает левоассоциативную цепочку в дерево BinaryOperation.
     *
     * Поддерживает два режима:
     *
     * 1. **Пары op/value** (для Comparison / Additive / Multiplicative):
     *    `[op1, value1, op2, value2, ...]`, где op — TokenInterface или
     *    строка (результат `CompareOp`/`AddOp`/`MulOp`). Параметр
     *    `$defaultOperator` должен быть null.
     *
     * 2. **Список значений** (для OrExpr / AndExpr, где оператор выбрасывается
     *    через `::T_OR::` / `::T_AND::`): `[value1, value2, ...]`, все value —
     *    Expression. Параметр `$defaultOperator` задаёт оператор (`'OR'` /
     *    `'AND'`).
     *
     * @param ?string $defaultOperator Если задан — все элементы считаются
     *                                 значениями (без оператора в потоке);
     *                                 иначе — парами [op, value].
     */
    public static function foldLeftBinaryChain(
        Expression $left,
        mixed $rest,
        ?string $defaultOperator = null
    ): Expression {
        $items = self::normalizeRepeat($rest);
        $count = \count($items);

        if ($count === 0) {
            return $left;
        }

        // Режим «список значений» — все элементы Expression, оператор общий.
        if ($defaultOperator !== null) {
            $allValues = true;
            foreach ($items as $item) {
                if ($item instanceof TokenInterface || \is_string($item)) {
                    $allValues = false;
                    break;
                }
            }

            if ($allValues) {
                foreach ($items as $value) {
                    if ($value instanceof Expression) {
                        $left = new BinaryOperation($defaultOperator, $left, $value);
                    }
                }
                return $left;
            }
        }

        // Режим «пары op/value».
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $opRaw = $items[$i];
            $right = $items[$i + 1];

            if ($defaultOperator !== null) {
                $op = $defaultOperator;
            } elseif ($opRaw instanceof TokenInterface) {
                /** @var \Phplrt\Lexer\Token\Token $opRaw */
                $op = \strtoupper($opRaw->getValue());
            } elseif (\is_string($opRaw)) {
                $op = \strtoupper($opRaw);
            } else {
                continue;
            }

            if (!$right instanceof Expression) {
                continue;
            }

            $left = new BinaryOperation($op, $left, $right);
        }

        return $left;
    }
}