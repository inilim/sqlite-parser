<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Resolver;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;

/**
 * Схема, заданная массивом: имя таблицы → список колонок.
 *
 * Может быть построена вручную (передачей массива в конструктор) либо из
 * разобранных `CREATE TABLE`-инструкций:
 *
 *     $schema = new ArraySchema([
 *         'users' => ['id', 'name', 'email'],
 *     ]);
 *
 *     // либо из скрипта:
 *     $schema = ArraySchema::fromScript($parser->parse($createSql));
 *
 * Схема иммутабельна: метод {@see self::addTable()} возвращает новый экземпляр.
 */
final class ArraySchema implements SchemaProvider
{
    /** @var array<string, list<string>> */
    private readonly array $tables;

    /**
     * @param array<string, list<string>> $tables
     */
    public function __construct(array $tables = [])
    {
        $this->tables = $tables;
    }

    /**
     * Строит схему по списку `CREATE TABLE`-инструкций.
     *
     * @param list<CreateTableStatement> $statements
     */
    public static function fromCreateStatements(array $statements): self
    {
        $tables = [];
        foreach ($statements as $stmt) {
            $columns = [];
            foreach ($stmt->columns as $column) {
                $columns[] = $column->name;
            }
            $tables[$stmt->table] = $columns;
        }

        return new self($tables);
    }

    /**
     * Строит схему из разобранного SQL-скрипта, извлекая из него все
     * `CREATE TABLE`-инструкции (остальные игнорируются).
     */
    public static function fromScript(Script $script): self
    {
        $createStatements = [];
        foreach ($script->statements as $statement) {
            if ($statement instanceof CreateTableStatement) {
                $createStatements[] = $statement;
            }
        }

        return self::fromCreateStatements($createStatements);
    }

    public function getTableColumns(string $tableName): ?array
    {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * Возвращает новый экземпляр схемы с добавленной/заменённой таблицей.
     *
     * @param list<string> $columns
     */
    public function addTable(string $tableName, array $columns): self
    {
        $tables = $this->tables;
        $tables[$tableName] = $columns;

        return new self($tables);
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->tables;
    }
}