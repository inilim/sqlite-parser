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
 *
 * {@see self::normalizeRepeat()} приводит все три формы к плоскому массиву
 * `[op1, value1, op2, value2, ...]`, а {@see self::foldLeftBinaryChain()}
 * сворачивает его в левоассоциативную цепочку BinaryOperation.
 */
final class Reducers
{
    /**
     * Приводит результат повторения к плоскому массиву [op, value, op, value, ...].
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

        $first = $rest[0] ?? null;

        // flat / flat-inner: [op, value] или [op1, value1, op2, value2, ...]
        if ($first instanceof TokenInterface || \is_string($first)) {
            return \array_values($rest);
        }

        // nested: [[op1, value1], [op2, value2], ...]
        $flat = [];
        foreach ($rest as $pair) {
            if (\is_array($pair) && \count($pair) >= 2) {
                $flat[] = $pair[0];
                $flat[] = $pair[1];
            }
        }

        return $flat;
    }

    /**
     * Сворачивает пары [op, value] в левоассоциативную цепочку BinaryOperation.
     *
     * @param ?string $defaultOperator Если задан — используется для всех пар,
     *                                 иначе оператор берётся из первого элемента пары
     *                                 (Token или string).
     */
    public static function foldLeftBinaryChain(
        Expression $left,
        mixed $rest,
        ?string $defaultOperator = null
    ): Expression {
        $items = self::normalizeRepeat($rest);
        $count = \count($items);

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $opRaw = $items[$i];
            $right = $items[$i + 1];

            if ($defaultOperator !== null) {
                $op = $defaultOperator;
            } elseif ($opRaw instanceof TokenInterface) {
                $op = \strtoupper($opRaw->value);
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