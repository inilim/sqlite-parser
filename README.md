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
use Inilim\SqliteParser\Parser\SqliteParser;
use Inilim\SqliteParser\Ast\Script;
use Phplrt\Source\StringSource;

$parser = new SqliteParser();

/** @var Script $script */
$script = $parser->parse(StringSource::createFromString(
    'SELECT id, name FROM users WHERE active = 1;'
));

foreach ($script->statements as $statement) {
    // $statement — SelectStatement, InsertStatement, CreateTableStatement...
}
```

Корневой узел — `Script` (список инструкций, разделённых `;`).
Одиночная инструкция — `$script->statements[0]`.

Все AST-узлы реализуют `JsonSerializable`, поэтому `json_encode($script)`
даёт готовое к сравнению / сериализации представление для тестов и отладки.

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

### CI

GitHub Actions (`.github/workflows/ci.yml`) на каждый push/PR выполняет:

1. `composer grammar:check` — валидация грамматики.
2. `composer grammar:build` + `git diff --exit-code -- src/Parser/SqliteParser.php` —
   проверка, что скомпилированный парсер соответствует исходной грамматике
   (ловит устаревший `src/Parser/SqliteParser.php`).
3. `composer tests` — PHPUnit.
4. `composer stan` — статический анализ (PHPStan).

Полный поэтапный roadmap — в [`PLAN.md`](PLAN.md).