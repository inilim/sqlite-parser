<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Resolver;

use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Ast\Statement;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\CreateViewStatement;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\ExplainStatement;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\ReturningClause;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Ast\Statement\WithClause;
use Inilim\SqliteParser\Printer\SqlPrinter;

/**
 * Определяет имена выходных колонок SQL-инструкции.
 *
 * Возвращает плоский список имён в порядке выходного списка — так же, как
 * это делает SQLite (см. `sqlite3_column_name()`).
 *
 * Поддерживаются:
 *
 *   1. **SELECT** (в т.ч. сложные):
 *      - `SELECT a AS x, b`                  → `['x', 'b']`
 *      - `SELECT a.b, COUNT(*)`              → `['b', 'COUNT(*)']`
 *      - `SELECT * FROM t`                   → все колонки `t` из {@see SchemaProvider}
 *      - `SELECT * FROM t1 JOIN t2 ...`      → `t1`, затем `t2` (в порядке FROM/JOIN)
 *      - `SELECT * FROM (SELECT ...) AS t`   → колонки внутреннего SELECT
 *      - `WITH cte AS (...) SELECT * FROM cte` → колонки CTE
 *      - `WITH a AS (...), b AS (SELECT * FROM a) SELECT * FROM b` → цепочка CTE
 *      - `SELECT ... UNION ALL SELECT ...`   → имена из первого SELECT
 *      - `EXPLAIN [QUERY PLAN] SELECT ...`   → разворачивается во вложенный statement
 *
 *   2. **DML с `RETURNING`**:
 *      - `INSERT ... RETURNING *`            → колонки таблицы-цели
 *      - `INSERT ... RETURNING id, name`     → `['id', 'name']`
 *      - `UPDATE ... RETURNING * AS x`       → раскрытие `*` по таблице-цели
 *      - `DELETE ... RETURNING ...`          → аналогично
 *
 *   3. **DDL, порождающий выходной набор**:
 *      - `CREATE VIEW v [(cols)] AS SELECT ...` → колонки SELECT (или явные `cols`)
 *      - `CREATE TABLE t AS SELECT ...`      → колонки SELECT
 *
 * Всё остальное (`CREATE TABLE ... (cols)`, `CREATE INDEX`, `DROP`, `ALTER`,
 * `PRAGMA`, транзакции, `ATTACH`/`DETACH` и т.п.) возвращает `[]` — у этих
 * инструкций нет выходного набора колонок.
 *
 * Ограничения (best-effort, честно документировано):
 *
 *   - `SELECT t.*` / `RETURNING t.*` — текущий AST не хранит квалификатор
 *     у {@see Star}, поэтому `t.*` обрабатывается как `*`.
 *   - Рекурсивные CTE (`WITH RECURSIVE r AS (SELECT ... FROM r) SELECT ...`)
 *     не разворачиваются: тело CTE ссылается на само себя, и раскрытие
 *     `*` из `r` внутри тела даст `[]`.
 *   - `WITH ... INSERT/UPDATE/DELETE` — парсер такие конструкции не производит
 *     (у `InsertStatement`/`UpdateStatement`/`DeleteStatement` нет поля `with`),
 *     поэтому в резолвере они не поддерживаются. Обычный `WITH ... SELECT`
 *     работает.
 *   - Неизвестная таблица в `SchemaProvider` — `SELECT *` для неё даёт `[]`
 *     (best-effort). Чтобы получить полный список, передайте схему,
 *     покрывающую все таблицы запроса (например, `ArraySchema::fromScript()`
 *     по SQL-дампу с `CREATE TABLE`).
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

            $statement instanceof InsertStatement => $this->resolveReturning(
                $statement->returning,
                $statement->table,
                $inheritedSchema,
            ),
            $statement instanceof UpdateStatement => $this->resolveReturning(
                $statement->returning,
                $statement->table,
                $inheritedSchema,
            ),
            $statement instanceof DeleteStatement => $this->resolveReturning(
                $statement->returning,
                $statement->table,
                $inheritedSchema,
            ),

            $statement instanceof CreateViewStatement => $this->resolveCreateView($statement, $inheritedSchema),
            $statement instanceof CreateTableStatement && $statement->asSelect !== null
                => $this->resolveStatement($statement->asSelect, $inheritedSchema),

            // EXPLAIN / EXPLAIN QUERY PLAN формально возвращают колонки плана
            // (addr, opcode, ... / id, parent, ...), но для практической задачи
            // «какие колонки выдаст запрос» полезнее развернуть во вложенный
            // statement.
            $statement instanceof ExplainStatement => $this->resolveStatement($statement->statement, $inheritedSchema),

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
     * @param array<string, list<string>> $inheritedSchema
     *
     * @return list<string>
     */
    private function resolveCreateView(CreateViewStatement $view, array $inheritedSchema): array
    {
        // Явный список колонок `CREATE VIEW v (a, b) AS SELECT ...` затеняет
        // имена из SELECT.
        if ($view->columns !== null) {
            return $view->columns;
        }

        return $this->resolveStatement($view->select, $inheritedSchema);
    }

    /**
     * Разрешает `RETURNING`-секцию DML-инструкции.
     *
     * `RETURNING *` раскрывается по колонкам **таблицы-цели** (а не по
     * FROM/JOIN списку — у INSERT/UPDATE/DELETE его и нет).
     *
     * @param array<string, list<string>> $localSchema
     *
     * @return list<string>
     */
    private function resolveReturning(?ReturningClause $returning, string $targetTable, array $localSchema): array
    {
        if ($returning === null) {
            return [];
        }

        $targetColumns = $this->getTableColumns($targetTable, $localSchema);
        $expandStar = static fn (): array => $targetColumns ?? [];

        $result = [];
        foreach ($returning->columns as $resultColumn) {
            foreach ($this->resolveResultColumnWithStar($resultColumn, $expandStar) as $name) {
                $result[] = $name;
            }
        }

        return $result;
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
        return $this->resolveResultColumnWithStar(
            $resultColumn,
            fn (): array => $this->expandStar($select, $localSchema),
        );
    }

    /**
     * @param callable(): list<string> $expandStar
     *
     * @return list<string>
     */
    private function resolveResultColumnWithStar(ResultColumn $resultColumn, callable $expandStar): array
    {
        if ($resultColumn->alias !== null) {
            return [$resultColumn->alias];
        }

        $expression = $resultColumn->expression;

        if ($expression instanceof ColumnReference) {
            return [$expression->name];
        }

        if ($expression instanceof Star) {
            return $expandStar();
        }

        // Для остальных выражений (Literal, FunctionCall, BinaryOperation,
        // CaseExpression, Subquery, ...) SQLite подставляет текст выражения
        // как имя колонки.
        return [$this->printer->print($expression)];
    }

    /**
     * Раскрывает `*` в контексте SELECT: сначала колонки базовой таблицы
     * FROM, затем — каждой JOIN-таблицы (в порядке FROM/JOIN, как в SQLite).
     *
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

        // Явные псевдонимы колонок: `(SELECT ...) AS t (a, b)`.
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
     * Строит локальную «теневую» схему из списка CTE. Каждая CTE видит
     * ранее объявленные CTE этого же `WITH` (chained CTE).
     *
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
                $innerSchema = array_merge($inheritedSchema, $result);
                $cteColumns = $this->resolveStatement($cte->query, $innerSchema);
            }

            $result[$cte->name] = $cteColumns;
        }

        return $result;
    }
}