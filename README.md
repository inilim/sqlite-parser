# inilim/sqlite-parser

SQLite SQL parser, построенный на [`phplrt`](https://github.com/phplrt/phplrt).
Принимает текст SQL-скрипта и возвращает **типизированное AST** —
иерархию `Inilim\SqliteParser\Ast\*`, а не единственный generic-`Node`.

Целевая версия диалекта: **SQLite 3.54.\***.

## Install

```bash
composer require inilim/sqlite-parser
```

## Usage

```php
use Inilim\SqliteParser\Parser\Parser;
use Inilim\SqliteParser\Ast\Script;

$parser = new Parser();

/** @var Script $script */
$script = $parser->parse('SELECT id, name FROM users WHERE active = 1;');

foreach ($script->statements as $statement) {
    // $statement — SelectStatement, InsertStatement, CreateTableStatement...
}
```

Корневой узел — `Script` (список инструкций, разделённых `;`).
Одиночная инструкция — `$script->statements[0]`.

Все AST-узлы реализуют `JsonSerializable`, поэтому `json_encode($script)`
даёт готовое к сравнению / сериализации представление для тестов и отладки.

### Низкоуровневый API

Если нужен полный контроль над источником (файл, поток, произвольная
реализация `ReadableInterface`), используйте сгенерированный
`Inilim\SqliteParser\Parser\SqliteParser` напрямую:

```php
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\FileSource;

$parser = new SqliteParser();
$script = $parser->parse(FileSource::createFromPath(__DIR__ . '/schema.sql'));
```

Экземпляр `SqliteParser` можно передать в `Parser` через конструктор,
если требуется переиспользовать один и тот же низкоуровневый парсер:

```php
$parser = new \Inilim\SqliteParser\Parser\Parser(new \Inilim\SqliteParser\Parser\SqliteParser());
$script = $parser->parse('SELECT 1;');
```

## Supported SQL

Целевой диалект — SQLite **3.54.\***.

Реализовано:

- **SELECT** — выражения, функции, `CAST`, `CASE`, `JOIN`, `GROUP BY`/`HAVING`,
  `DISTINCT`, `UNION`/`INTERSECT`/`EXCEPT`, CTE (`WITH [RECURSIVE]`),
  оконные функции (`OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE ...)`).
- **DML** — `INSERT` (в т.ч. `OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK`,
  `DEFAULT VALUES`, `RETURNING`), `UPDATE`, `DELETE`, `UPSERT`
  (`ON CONFLICT (...) DO UPDATE ... | DO NOTHING`).
- **DDL** — `CREATE TABLE` (с `WITHOUT ROWID`, `STRICT`, `AUTOINCREMENT`,
  `REFERENCES`, `CHECK`, `DEFAULT`), `CREATE INDEX`, `CREATE VIEW`,
  `CREATE TRIGGER`, `ALTER TABLE`, `DROP TABLE|INDEX|VIEW|TRIGGER`.
- **Utility** — `PRAGMA` (в формах `= value` и `(value)`), `ATTACH`/`DETACH`,
  `BEGIN`/`COMMIT`/`ROLLBACK`/`END TRANSACTION`, `SAVEPOINT`/`RELEASE`/
  `ROLLBACK TO`, `EXPLAIN [QUERY PLAN]`.

### Известные ограничения

- `END;` без `TRANSACTION` не поддерживается — конфликтует с `BEGIN ... END`
  (тело триггера) и `CASE ... END`. Для закрытия транзакции используйте
  `COMMIT` либо `END TRANSACTION [name]`.
- Функции с ключевыми именами (`REPLACE`, `LEFT`, `RIGHT`) в `FunctionCall`
  не поддерживаются.
- Параметризованные типы в `CAST` (`VARCHAR(10)`) не поддерживаются — только
  одно слово.

## Development

```bash
composer install
composer grammar:check   # валидация грамматики
composer grammar:build   # регенерация src/Parser/SqliteParser.php
composer grammar:diff    # build + проверка, что сгенерированный парсер не «дрейфует»
composer tests           # PHPUnit
composer stan            # PHPStan (level 8)
composer ci              # весь пайплайн: check + diff + tests + stan
```