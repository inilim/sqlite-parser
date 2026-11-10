<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Resolver;

/**
 * Источник информации о колонках таблиц.
 *
 * Используется {@see ColumnResolver} для раскрытия `SELECT *` и
 * `SELECT * FROM t1` (где `t1` — CTE или реальная таблица).
 *
 * Реализация не обязана знать обо всех таблицах: если таблица неизвестна,
 * метод должен вернуть `null`. Это позволяет спокойно резолвить запросы
 * к представлениям (`VIEW`), временным таблицам и т.п.
 */
interface SchemaProvider
{
    /**
     * Возвращает список имён колонок таблицы в порядке их объявления.
     *
     * @return list<string>|null `null`, если таблица неизвестна.
     */
    public function getTableColumns(string $tableName): ?array;
}