<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Resolver;

use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Ast\Statement\WithClause;
use Inilim\SqliteParser\Printer\SqlPrinter;

/**
 * Определяет имена выходных колонок SELECT-запроса.
 *
 * Возвращает плоский список имён в порядке `SELECT`-списка — так же, как
 * это делает SQLite (см. `sqlite3_column_name()`).
 *
 * Поддерживаемые случаи:
 *
 *   - `SELECT a AS x, b`                  → `['x', 'b']`
 *   - `SELECT a.b, COUNT(*)`             → `['b', 'COUNT(*)']`
 *   - `SELECT * FROM t`                  → все колонки `t` из {@see SchemaProvider}
 *   - `SELECT * FROM t1 JOIN t2 ...`     → все колонки `t1`, затем `t2` (в порядке FROM/JOIN)
 *   - `SELECT * FROM (SELECT ...) AS t`  → колонки внутреннего SELECT
 *   - `WITH cte AS (...) SELECT * FROM cte` → колонки CTE
 *   - `SELECT ... UNION ALL SELECT ...`  → имена берутся из первого SELECT
 *
 * Если таблица неизвестна `SchemaProvider`-у, `SELECT *` для неё просто
 * ничего не добавляет (best-effort). Если нужна строгая проверка — используйте
 * схему, покрывающую все таблицы запроса.
 *
 * Ограничение AST: `SELECT t.*` пока не поддерживается парсером (звёздочка
 * не хранит квалификатор), поэтому `t.*` обрабатывается как `*`.
 *
 * Пример:
 *
 *     $schema   = ArraySchema::fromScript($parser->parse($createSql));
 *     $resolver = new ColumnResolver($schema);
 *     $columns  = $resolver->resolve($parser->parse($selectSql)->statements[0]);
 */
final class ColumnResolver
{
    private readonly SchemaProvider $schema;
    private readonly SqlPrinter $printer;

    public function __construct(SchemaProvider $schema, ?SqlPrinter $printer = null)
    {
        $this->schema = $schema;
        $this->printer = $printer ?? new SqlPrinter();
    }

    /**
     * @return list<string>
     */
    public function resolve(Statement $statement): array
    {
        return $this->resolveStatement($statement, []);
    }

    /**
     * @param array<string, list<string>> $inheritedSchema
     *
     * @return list<string>
     */
    private function resolveStatement(Statement $statement, array $inheritedSchema): array
    {
        return match (true) {
            $statement instanceof SelectStatement => $this->resolveSelect($statement, $inheritedSchema),
            $statement instanceof CompoundSelect => $this->resolveCompoundSelect($statement, $inheritedSchema),
            default => [],
        };
    }

    /**
     * @param array<string, list<string>> $inheritedSchema
     *
     * @return list<string>
     */
    private function resolveSelect(SelectStatement $select, array $inheritedSchema): array
    {
        $localSchema = array_merge(
            $inheritedSchema,
            $this->buildCteSchema($select->with, $inheritedSchema),
        );

        $result = [];
        foreach ($select->columns as $resultColumn) {
            foreach ($this->resolveResultColumn($resultColumn, $select, $localSchema) as $name) {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $inheritedSchema
     *
     * @return list<string>
     */
    private function resolveCompoundSelect(CompoundSelect $compound, array $inheritedSchema): array
    {
        $localSchema = array_merge(
            $inheritedSchema,
            $this->buildCteSchema($compound->with, $inheritedSchema),
        );

        if ($compound->selects === []) {
            return [];
        }

        // Имена выходных колонок составного запроса определяются первым SELECT.
        return $this->resolveSelect($compound->selects[0], $localSchema);
    }

    /**
     * @param array<string, list<string>> $localSchema
     *
     * @return list<string>
     */
    private function resolveResultColumn(
        ResultColumn $resultColumn,
        SelectStatement $select,
        array $localSchema
    ): array {
        if ($resultColumn->alias !== null) {
            return [$resultColumn->alias];
        }

        $expression = $resultColumn->expression;

        if ($expression instanceof ColumnReference) {
            return [$expression->name];
        }

        if ($expression instanceof Star) {
            return $this->expandStar($select, $localSchema);
        }

        // Для остальных выражений (Literal, FunctionCall, BinaryOperation, ...)
        // SQLite подставляет текст выражения как имя колонки.
        return [$this->printer->print($expression)];
    }

    /**
     * @param array<string, list<string>> $localSchema
     *
     * @return list<string>
     */
    private function expandStar(SelectStatement $select, array $localSchema): array
    {
        $result = [];

        $fromColumns = $this->getColumnsForTableReference($select->from, $localSchema);
        if ($fromColumns !== null) {
            foreach ($fromColumns as $column) {
                $result[] = $column;
            }
        }

        foreach ($select->joins as $join) {
            $joinColumns = $this->getColumnsForTableReference($join->table, $localSchema);
            if ($joinColumns !== null) {
                foreach ($joinColumns as $column) {
                    $result[] = $column;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $localSchema
     *
     * @return list<string>|null
     */
    private function getColumnsForTableReference(?TableReference $reference, array $localSchema): ?array
    {
        if ($reference === null) {
            return null;
        }

        if ($reference->columnAliases !== null) {
            return $reference->columnAliases;
        }

        if ($reference->name !== null) {
            return $this->getTableColumns($reference->name, $localSchema);
        }

        if ($reference->subquery !== null) {
            return $this->resolveStatement($reference->subquery, $localSchema);
        }

        return null;
    }

    /**
     * @param array<string, list<string>> $localSchema
     *
     * @return list<string>|null
     */
    private function getTableColumns(string $tableName, array $localSchema): ?array
    {
        // CTE-таблица с тем же именем «затеняет» реальную таблицу из схемы.
        if (isset($localSchema[$tableName])) {
            return $localSchema[$tableName];
        }

        return $this->schema->getTableColumns($tableName);
    }

    /**
     * @param array<string, list<string>> $inheritedSchema
     *
     * @return array<string, list<string>>
     */
    private function buildCteSchema(?WithClause $with, array $inheritedSchema): array
    {
        if ($with === null) {
            return [];
        }

        $result = [];
        foreach ($with->ctes as $cte) {
            if ($cte->columns !== null) {
                $cteColumns = $cte->columns;
            } else {
                // CTE видит ранее объявленные CTE этого же WITH.
                $innerSchema = array_merge($inheritedSchema, $result);
                $cteColumns = $this->resolveStatement($cte->query, $innerSchema);
            }

            $result[$cte->name] = $cteColumns;
        }

        return $result;
    }
}