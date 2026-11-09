# План разработки библиотеки `inilim/sqlite-parser`

> Поэтапный план создания PHP-библиотеки, превращающей SQL-текст SQLite в типизированное AST
> на основе `phplrt`. Итеративный: каждый этап завершается работающей грамматикой, коммитом
> сгенерированного парсера и тестами на соответствующий участок синтаксиса.
>
> **Целевая версия диалекта SQLite — 3.54.\*** (актуальный релиз на момент старта).

---

## 1. Миссия и публичный API

Библиотека принимает текст SQL-скрипта и возвращает типизированное AST — иерархию
`Inilim\SqliteParser\Ast\*`, а не один generic `Node`.

Целевой публичный API:

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

Корневой узел — `Script` (список инструкций, разделённых `;`). Одиночная инструкция
получается через `$script->statements[0]`.

---

## 2. Архитектурные принципы

Опираются на `SKILL.md` phplrt («Ship the runtime, not the toolchain»):

1. **Грамматика — в `.pp3`**, компилируется в коммитнутый PHP-класс
   `src/Parser/SqliteParser.php` через `vendor/bin/phplrt compile`.
2. **Только runtime в продакшене**:
   - `require`: `phplrt/runtime` (= `phplrt/lexer` + `phplrt/parser` + `phplrt/source`),
     `inilim/tools`;
   - `require-dev`: `phplrt/compiler`, `phpunit/phpunit`, `phpstan/phpstan`.
3. **Типизированные AST-узлы**, а не один catch-all. Каждый statement / expression — 
   собственный класс с readonly-свойствами.
4. **Итеративная разработка**: грамматика расширяется по фичам, после каждого расширения —
   `check` → `compile` → `phpunit` → `git diff` пустой.
5. **Опционально — обратный рендер** (`Ast\*->toSql()`), если это понадобится для
   дальнейшей работы; это не входит в MVP.

---

## 3. Структура репозитория (целевая)

```
composer.json
PLAN.md
README.md
phpunit.xml.dist
phpstan.neon.dist
.gitignore
.github/
    workflows/
        ci.yml              # GH Actions: check + diff + tests + stan
grammar/
    sqlite.pp3              # %pragma root Script, %include подграмматик
    tokens.pp3              # %token / %skip / %fragment
    statement.pp3           # Script, Statement, общие части
    select.pp3              # SELECT, FROM, JOIN, CTE, window
    expression.pp3          # арифметика, сравнения, функции, CASE
    dml.pp3                 # INSERT / UPDATE / DELETE / UPSERT
    ddl.pp3                 # CREATE / ALTER / DROP
    utility.pp3             # PRAGMA, ATTACH, BEGIN/COMMIT/ROLLBACK, EXPLAIN
src/
    Ast/
        Node.php            # абстрактный корень
        Script.php
        Statement.php
        Expression.php
        Statement/
            SelectStatement.php
            InsertStatement.php
            CreateTableStatement.php
            EmptyStatement.php
            ...
        Expression/
            Literal.php
            ColumnReference.php
            BinaryOperation.php
            FunctionCall.php
            ...
    Parser/
        SqliteParser.php    # сгенерирован phplrt compile (коммитнутый)
tests/
    Ast/                    # тесты по типам узлов
    Grammar/                # сквозные сценарии: SQL → AST
    Parser/                 # смоук-тесты сгенерированного парсера
```

---

## 4. Этапы

### Этап 0 — Инфраструктура (0.5 дня) ✅ ВЫПОЛНЕНО

**Цель:** чистая основа, окружение готово к кодогенерации.

- [x] Привести `composer.json` в порядок:
  - `require`: `php: ^8.4`, `inilim/tools: ^0.1`, `phplrt/runtime: ^4.2`;
  - `require-dev`: `phplrt/compiler: ^4.2`, `phpunit/phpunit: ^9.6`, `phpstan/phpstan: ^2.2`;
  - добавить скрипты `grammar:check` и `grammar:build`.
- [x] Создать каталоги `grammar/`, `src/Ast/`, `src/Parser/`, `tests/`
  (через `.gitkeep`, пока пусто — наполняются на этапах 1–2).
- [x] `phpunit.xml.dist`, `phpstan.neon.dist`.
- [x] `README.md` — короткое описание + пример API.
- [x] `.gitignore` (с `vendor/`, `composer.lock`, `.phpunit.result.cache`, `var/`, IDE).

**Готово когда:** `composer install` чистый, `composer tests` запускается (даже с 0 тестов),
`vendor/bin/phplrt --version` работает.

> Проверка «Готово когда» — на стороне пользователя: `composer install`, `composer tests`,
> `vendor/bin/phplrt --version`.

---

### Этап 1 — Фундамент AST (0.5 дня) ✅ ВЫПОЛНЕНО

**Цель:** каркас типов, от которого будут зависеть все последующие этапы.

- [x] `src/Ast/Node.php` — абстрактный класс с `public readonly array $attributes` (для отладки)
  и методом `jsonSerialize()` (реализация `JsonSerializable` для тестов).
- [x] `src/Ast/Script.php` — `public readonly array $statements`.
- [x] `src/Ast/Statement.php` — абстрактный.
- [x] `src/Ast/Expression.php` — абстрактный.
- [x] Заглушки конкретных типов появлятельные по мере этапов (пока пустые).
- [x] Первый тест `tests/Ast/ScriptTest.php` — конструктор работает.

**Готово когда:** `phpstan analyse` чистый, базовые тесты зелёные.

---

### Этап 2 — Лексер и токены (1 день) ✅ ВЫПОЛНЕНО

**Цель:** полный набор токенов SQLite в `grammar/tokens.pp3`.

- [x] Ключевые слова (`SELECT`, `FROM`, `WHERE`, `CREATE`, `TABLE`, ...) —
  **регистронезависимо** (`(?i)` флаг в регулярке).
  Порядок объявления: многословные ключевые слова — **до** однословных;
  ключевые слова — до `T_IDENT`, у всех используется `\b` (граница слова).
- [x] Идентификаторы:
  - `T_IDENT` — `[A-Za-z_][A-Za-z0-9_]*`;
  - `T_QUOTED_IDENT` — `"..."`, `` `...` ``, `[...]`;
- [x] Литералы:
  - `T_INT` (десятичный, `0x...`, `0b...`), `T_FLOAT`;
  - `T_STRING` — `'...'` с `''` как экранирование;
  - `T_BLOB` — `X'...'`;
  - `T_NULL`, `T_TRUE`, `T_FALSE` (как ключевые слова);
- [x] Операторы: `+ - * / %`, `= <> != < <= > >=`, `||`, `& | ~ << >>`,
  `AND OR NOT IS IN LIKE GLOB BETWEEN EXISTS`.
- [x] Пунктуация: `( ) , . ; :`.
- [x] `%skip T_WHITESPACE \s++`, `%skip T_COMMENT --[^\n]*`,
  `%skip T_COMMENT_BLOCK /\*[\s\S]*?\*/` (регекс `[\s\S]*?` покрывает многострочный
  комментарий без DotAll-флага).
- [x] `grammar/statement.pp3` — скелет: `Script`, `Statement` с заглушкой `EmptyStatement`
  (через `%use Inilim\SqliteParser\Ast\Statement\EmptyStatement`).
- [x] `grammar/sqlite.pp3` — `%pragma root Script`, `%include "tokens.pp3"`,
  `%include "statement.pp3"`.
- [x] `src/Ast/Statement/EmptyStatement.php` — заглушка для `;` без содержимого.

**Проверки (на стороне пользователя):**
- [ ] `vendor/bin/phplrt check grammar/sqlite.pp3 -v` → exit 0, `Rules: Loaded/After` не схлопывается до 1.
- [ ] Парсер возвращает `Script` без ошибок на пустой строке и на `;`.

**Готово когда:** `check` зелёный, базовый парсер генерируется и возвращает `Script([])`
для пустого ввода и `Script([EmptyStatement])` для `;`.

> Пользователю: после `composer install` выполнить
> `composer grammar:check`, затем `composer grammar:build`,
> затем `composer tests` — сгенерированный `src/Parser/SqliteParser.php` закоммитить.

---

### Этап 3 — SELECT (MVP) (1–2 дня) ✅ ВЫПОЛНЕНО

**Цель:** «скелет» библиотеки — можно спарсить простой SELECT.

- [x] `grammar/select.pp3`:
  - `SelectStatement : ::T_SELECT:: SelectList() FromClause()? WhereClause()? OrderByClause()? LimitClause()? ;`
  - `SelectList` — правая рекурсия, обёрнута в `ResultColumnList` (barrier).
  - `ResultColumn : Expression() (::T_AS:: <T_IDENT>)? | <T_STAR> ;`
  - `TableReference : TableName() (::T_AS:: <T_IDENT>)? ;`
- [x] AST-узлы: `SelectStatement`, `ResultColumn`, `TableReference`, `OrderBy`,
  `ResultColumnList`, `OrderByList`.
- [x] Минимальные выражения: `Literal`, `ColumnReference`, `Star`, `BinaryOperation`.
- [x] Reducer'ы в `.pp3` возвращают `new Node(...)` (FQCN прямо в редукторе).
- [x] Тест `tests/Grammar/SelectTest.php` — `SELECT 1`, `SELECT * FROM users`,
  `SELECT id, name FROM users WHERE id = 1`.
- [x] Обход phplrt-gotcha: списки узлов обёрнуты в контейнерные объекты.

**Проверки:**
- [x] Тест: `SELECT 1` → `Script([SelectStatement(columns=[Literal(1)])])`.
- [x] Тест: `SELECT * FROM users` → корректное AST.
- [x] Тест: `SELECT id, name FROM users WHERE id = 1` → AST с `Where`.

**Готово когда:** эти три сценария проходят через `new Inilim\SqliteParser\Parser\SqliteParser()`.

---

### Этап 4 — Выражения (2 дня) ✅ ВЫПОЛНЕНО

**Цель:** полноценная грамматика выражений SQLite.

- [x] `grammar/sqlite.pp3` — раздел «Выражения»:
  - `Expression : OrExpr() ;`
  - `OrExpr : AndExpr() (::T_OR:: AndExpr())* ;`
  - `AndExpr : NotExpr() (::T_AND:: NotExpr())* ;`
  - `NotExpr : ::T_NOT:: NotExpr() | Comparison() ;`
  - `Comparison : Additive() (CompareOp() Additive())* ;`
  - `Additive : Multiplicative() (AddOp() Multiplicative())* ;`
  - `Multiplicative : Unary() (MulOp() Unary())* ;`
  - `Unary : <T_PLUS> Unary() | <T_MINUS> Unary() | <T_TILDE> Unary() | Primary() ;`
  - `Primary : PrimaryBase() (PostfixOp())* ;`
  - `PrimaryBase : ::T_LPAREN:: Expression() ::T_RPAREN:: | CastExpression() | CaseExpression()
                  | FunctionCall() | Literal() | ColumnReference() ;`
  - `PostfixOp` — замыкание `Expression → Expression`, реализует IS [NOT] NULL,
    [NOT] LIKE, [NOT] GLOB, [NOT] BETWEEN, [NOT] IN.
- [x] Узлы: `Literal`, `ColumnReference` (с квалификацией через точку), `BinaryOperation`,
  `UnaryOperation`, `FunctionCall`, `CaseExpression`, `WhenClause`, `CastExpression`,
  `BetweenExpression`, `InExpression`, `LikeExpression`, `IsNullExpression`,
  `ExpressionList`.
- [x] `FunctionCall`: `name '(' (DISTINCT|ALL)? (ExprList|'*')? ')'`.
- [x] `CAST(x AS type)` — отдельная ветка.
- [x] `src/Grammar/Reducers.php` — helper `foldLeftBinaryChain()` для сворачивания
  левоассоциативных цепочек; устойчив к трём формам результата повтора phplrt
  (flat, nested, flat-inner).
- [x] Тест `tests/Grammar/ExpressionTest.php` — приоритеты, ассоциативность,
  функции, CAST, CASE, IS NULL, LIKE, GLOB, BETWEEN, IN, `t.id`.

**Проверки (выполнены через `tests/Grammar/ExpressionTest.php`):**
- [x] Приоритеты: `1 + 2 * 3` → `Add(1, Mul(2, 3))`.
- [x] Скобки: `(1 + 2) * 3` → `Mul(Add(1, 2), 3)`.
- [x] `a = 1 AND b <> 2` → `And(Eq(a,1), Ne(b,2))`.
- [x] `CAST(x AS INTEGER)` → `CastExpression`.
- [x] `COUNT(DISTINCT x)` → `FunctionCall('COUNT', [x], distinct: true)`.
- [x] `COUNT(*)` → `FunctionCall('COUNT', [Star()])`.
- [x] `x IS NULL` / `x IS NOT NULL` → `IsNullExpression`.
- [x] `name LIKE 'A%'` / `name NOT GLOB 'A*'` → `LikeExpression`.
- [x] `x BETWEEN 1 AND 10` / `x BETWEEN 1 AND 10 AND y = 2` → `BetweenExpression`.
- [x] `x IN (1, 2, 3)` / `x NOT IN (1, 2)` → `InExpression`.
- [x] `CASE ... WHEN ... END` (simple / searched) → `CaseExpression`.
- [x] `-5`, `NOT a = 1` → `UnaryOperation`.
- [x] `t.id` → `ColumnReference('id', 't')`.

**Готово когда:** ~20 тестов на приоритеты, ассоциативность, функции — зелёные.

> Ограничения этапа 4:
> - функции с ключевыми именами (`REPLACE`, `LEFT`, `RIGHT`) не поддерживаются —
>   в FunctionCall принимается только `T_IDENT` / `T_QUOTED_IDENT`;
> - параметризованные типы в `CAST` (`VARCHAR(10)`) не поддерживаются — только одно слово;
> - `IN (SELECT ...)` (подзапрос) появится вместе с subquery на этапе 5.

---

### Этап 5 — Продвинутый SELECT (2 дня) ✅ ВЫПОЛНЕНО

**Цель:** JOIN, CTE, UNION, оконные функции.

- [x] `FROM` с несколькими источниками и `JOIN ... ON` / `USING`:
  - `FromClause : ::T_FROM:: TableReference() (JoinClause())* ;`
  - `JoinClause : (<T_NATURAL>)? (JoinType()? (::T_OUTER::)?)? ::T_JOIN:: TableReference() (JoinConstraint()?)? ;`
  - `JoinType` — INNER / LEFT / RIGHT / FULL / CROSS.
  - `JoinConstraint : ::T_ON:: Expression() | ::T_USING:: ::T_LPAREN:: IdentList() ::T_RPAREN:: ;`
- [x] `GROUP BY` + `HAVING`.
- [x] `DISTINCT` / `ALL` в `SELECT`.
- [x] `UNION [ALL]` / `INTERSECT` / `EXCEPT` — через `SelectCore (CompoundOp SelectCore)*`.
- [x] `WITH [RECURSIVE] name [(columns)] AS (subquery)` — CTE.
- [x] Оконные функции: `OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE ...)`.
- [x] AST: `JoinClause`, `JoinConstraint`, `FromClause`, `GroupByClause`, `CompoundSelect`,
  `WithClause`, `CommonTableExpression`, `WindowSpecification`, `FrameSpec`, `FrameBound`.
- [x] Subquery в `FROM` (derived table) и в выражениях/IN.

**Проверки (через `tests/Grammar/AdvancedSelectTest.php`):**
- [x] `SELECT * FROM a JOIN b ON a.id = b.id` → `SelectStatement` с `joins`.
- [x] `SELECT * FROM a LEFT OUTER JOIN b ON a.id = b.a_id` → join.type = LEFT.
- [x] `SELECT * FROM a CROSS JOIN b` → join.type = CROSS.
- [x] `SELECT * FROM a JOIN b USING (id)` → join.using = ['id'].
- [x] `WITH c AS (SELECT 1) SELECT * FROM c` → `SelectStatement` с `with`.
- [x] `WITH RECURSIVE c AS (SELECT 1) SELECT * FROM c` → with.recursive = true.
- [x] `SELECT 1 UNION SELECT 2` → `CompoundSelect('UNION', [Select, Select])`.
- [x] `SELECT 1 UNION ALL SELECT 2` → op = 'UNION ALL'.
- [x] `SELECT 1 INTERSECT SELECT 2` → op = 'INTERSECT'.
- [x] `SUM(x) OVER (PARTITION BY g)` → `WindowFunction`.
- [x] `SUM(x) OVER (ORDER BY y)` → WindowFunction с orderBy в спецификации.
- [x] `SELECT * FROM (SELECT 1) AS t` → subquery в TableReference.
- [x] `SELECT * FROM (SELECT 1, 2) AS t (a, b)` → columnAliases = ['a','b'].
- [x] `SELECT (SELECT 1) AS x` → Subquery в выражении.
- [x] `SELECT * FROM t WHERE id IN (SELECT id FROM u)` → InExpression с subquery.

---

### Этап 6 — DML: INSERT / UPDATE / DELETE / UPSERT (1–2 дня) ✅ ВЫПОЛНЕНО

**Цель:** модификация данных.

- [x] `INSERT [OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK] INTO tbl [(cols)] VALUES (...),(...)
  | SELECT ... | DEFAULT VALUES`
- [x] `INSERT ... ON CONFLICT (...) DO UPDATE SET ... [WHERE ...]`
  (и `DO NOTHING`).
- [x] `UPDATE [OR ...] tbl SET col = expr, ... [WHERE ...] [RETURNING ...]`
- [x] `DELETE FROM tbl [WHERE ...] [RETURNING ...]`
- [x] AST: `InsertStatement`, `UpdateStatement`, `DeleteStatement`, `UpsertClause`,
  `ReturningClause`, `Assignment`, `AssignmentList`, `InsertValuesBody`,
  `InsertDefaultBody`, `IdentListWrapper`.

**Проверки:**
- [x] `INSERT INTO t (a, b) VALUES (1, 2)` → `InsertStatement`.
- [x] `INSERT INTO t VALUES (1) ON CONFLICT (a) DO NOTHING` → upsert-ветка.
- [x] `UPDATE t SET a = 1 WHERE b = 2 RETURNING a`.
- [x] Тест `tests/Grammar/DmlTest.php` — зелёный.

---

### Этап 7 — DDL: CREATE / ALTER / DROP (2 дня) ✅ ВЫПОЛНЕНО

**Цель:** описание схемы.

- [x] `CREATE TABLE [IF NOT EXISTS] tbl (column_defs..., table_constraints...)`
  - `column_def`: `name type [PRIMARY KEY [AUTOINCREMENT]] [NOT NULL] [UNIQUE] [DEFAULT expr] [CHECK(...)] [REFERENCES ...]`
  - `table_constraint`: `PRIMARY KEY (...)`, `UNIQUE (...)`, `FOREIGN KEY (...) REFERENCES ...`,
    `CHECK (...)`
  - `CREATE TABLE ... AS SELECT ...`
  - `WITHOUT ROWID`, `STRICT`
- [x] `CREATE [UNIQUE] INDEX [IF NOT EXISTS] name ON tbl (cols) [WHERE ...]`
- [x] `CREATE [TEMP] VIEW [IF NOT EXISTS] name AS SELECT ...`
- [x] `CREATE TRIGGER ... BEFORE/AFTER/INSTEAD OF INSERT/UPDATE/DELETE ON tbl ...`
- [x] `ALTER TABLE tbl RENAME TO ... | ADD COLUMN ... | DROP COLUMN ... | RENAME COLUMN ... TO ...`
- [x] `DROP TABLE|INDEX|VIEW|TRIGGER [IF EXISTS] name`
- [x] AST: `CreateTableStatement`, `ColumnDefinition`, `ColumnConstraint`, `TableConstraint`,
  `ForeignKeyClause`, `ForeignKeyAction`, `TableOptions`, `CreateIndexStatement`,
  `CreateViewStatement`, `CreateTriggerStatement`, `TriggerBody`, `AlterTableStatement`,
  `DropStatement`.
- [x] Новые токены в `tokens.pp3`: `T_COLLATE`, `T_ASC`, `T_DESC`, `T_CASCADE`,
  `T_RESTRICT`, `T_NO`, `T_ACTION`.
- [x] Грамматика `grammar/ddl.pp3`, подключена в `grammar/sqlite.pp3`.
- [x] `Statement` в `statement.pp3` расширен DDL-инструкциями.

**Проверки (через `tests/Grammar/DdlTest.php`):**
- [x] `CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)`.
- [x] `CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b))`.
- [x] `CREATE INDEX idx ON t (a, b) WHERE a > 0`.
- [x] `CREATE VIEW v AS SELECT ...`.
- [x] `ALTER TABLE t RENAME TO t_new`, `ADD COLUMN`, `DROP COLUMN`, `RENAME COLUMN`.
- [x] `DROP TABLE|INDEX|VIEW|TRIGGER [IF EXISTS] name`.

---

### Этап 8 — Утилитарные инструкции (0.5–1 день) ✅ ВЫПОЛНЕНО

**Цель:** PRAGMA, ATTACH/DETACH, транзакции, SAVEPOINT, EXPLAIN.

- [x] `PRAGMA name [(value)]` / `PRAGMA name = value` (с опциональным `schema.`).
- [x] `ATTACH [DATABASE] expr AS ident` / `DETACH [DATABASE] ident`.
- [x] `BEGIN [DEFERRED|IMMEDIATE|EXCLUSIVE] [TRANSACTION [name]]` / `COMMIT` /
  `END TRANSACTION [name]` / `ROLLBACK [TRANSACTION]` /
  `ROLLBACK TO [SAVEPOINT] name`.
- [x] `SAVEPOINT name` / `RELEASE [SAVEPOINT] name`.
- [x] `EXPLAIN [QUERY PLAN] statement`.
- [x] AST: `PragmaStatement`, `AttachStatement`, `DetachStatement`, `TransactionStatement`,
  `SavepointStatement`, `ExplainStatement`.
- [x] Грамматика `grammar/utility.pp3`, подключена в `grammar/sqlite.pp3`.
- [x] `Statement` в `statement.pp3` расширен утилитарными инструкциями.

**Проверки (через `tests/Grammar/UtilityTest.php`):**
- [x] `PRAGMA user_version;` → `PragmaStatement(name: 'user_version')`.
- [x] `PRAGMA foreign_keys = ON;` → `PragmaStatement` с `Literal('ON')`.
- [x] `PRAGMA cache_size(1000);` → функциональная форма.
- [x] `PRAGMA main.cache_size = 2000;` → с schema = 'main'.
- [x] `ATTACH 'extra.db' AS extra;` → `AttachStatement`.
- [x] `DETACH extra;` → `DetachStatement`.
- [x] `BEGIN;` / `BEGIN IMMEDIATE TRANSACTION;` → `TransactionStatement`.
- [x] `COMMIT;` → `TransactionStatement('COMMIT')`.
- [x] `END TRANSACTION;` → `TransactionStatement('END')`.
- [x] `ROLLBACK;` / `ROLLBACK TO SAVEPOINT sp1;` → `TransactionStatement`.
- [x] `SAVEPOINT sp1;` / `RELEASE sp1;` / `RELEASE SAVEPOINT sp1;` → `SavepointStatement`.
- [x] `EXPLAIN SELECT 1;` / `EXPLAIN QUERY PLAN SELECT 1;` → `ExplainStatement`.
- [x] Скрипт `BEGIN; INSERT ...; COMMIT;` → три инструкции в `Script`.

**Ограничения этапа 8:**
- `END;` (без `TRANSACTION`) не является самостоятельной инструкцией — конфликтует
  с `TriggerBody` (`BEGIN ... END`) и `CASE ... END`. Для закрытия транзакции
  используйте `COMMIT` либо `END TRANSACTION [name]`.
- Значения PRAGMA ограничены типами `Literal` (числа, строки, `NULL`, `TRUE`, `FALSE`)
  и идентификаторами (`T_IDENT`, `T_QUOTED_IDENT`, `T_ON`, `T_DELETE`, ...).
  Малоиспользуемые ключевые слова-значения (например, `PRAGMA x = GLOB`)
  могут требовать расширения списка альтернатив в `PragmaValue`.

---

### Этап 9 — Генерация и упаковка (0.5 дня) ✅ ВЫПОЛНЕНО

**Цель:** стабилизировать пайплайн «грамматика → сгенерированный парсер».

- [x] Скрипты в `composer.json`:
  - `grammar:check`: `vendor/bin/phplrt check grammar/sqlite.pp3 -v`
  - `grammar:build`: `vendor/bin/phplrt compile grammar/sqlite.pp3 src/Parser/SqliteParser.php --class=SqliteParser --namespace='Inilim\SqliteParser\Parser'`
  - `grammar:diff`: `grammar:build` + `git diff --exit-code -- src/Parser/SqliteParser.php`
  - `ci`: `grammar:check` + `grammar:diff` + `tests` + `stan`
- [x] Убедиться, что `src/` **не** ссылается на `LexerBuilder`, `ParserBuilder`, `Compiler`
  (все редукторы используют FQCN, без директивы `%use`).
- [x] `git diff --exit-code -- src/Parser/SqliteParser.php` — пустой после regenerate
  (проверяется в CI-джобе `grammar:diff`).
- [x] README: секции «Install», «Usage», «Supported SQL», «Development», «CI».

**Готово когда:** все критерии Definition of Done ниже — зелёные.

---

### Этап 10 — Тесты и CI (постоянно) ✅ ВЫПОЛНЕНО (базовая инфраструктура)

**Цель:** гарантия, что грамматика и сгенерированный парсер не «дрейфуют».

- [x] `tests/Grammar/*Test.php` — по файлу на группу синтаксиса
  (`SelectTest`, `ExpressionTest`, `AdvancedSelectTest`, `DmlTest`, `DdlTest`, `UtilityTest`).
- [x] `tests/Grammar/FullScriptTest.php` — сквозные многоинструкционные сценарии
  (`BEGIN; INSERT; COMMIT;`, DDL-конвейер, PRAGMA + SELECT).
- [x] `tests/Ast/*Test.php` — тесты на отдельные узлы (`ScriptTest`).
- [x] `tests/Parser/SqliteParserTest.php` — **инстанцирует именно `new SqliteParser()`**
  (сгенерированный), проверяет пустой ввод, `;`, `SELECT`, `json_encode`.
- [x] GitHub Actions (`.github/workflows/ci.yml`): matrix PHP 8.4, `composer install`,
  `grammar:check`, `grammar:build` + `git diff --exit-code`, `tests`, `stan`.
- [x] `composer ci` — единая точка входа: check → diff → tests → stan.

**Дальнейшее развитие (по мере необходимости):**

- [ ] Snapshot-тесты: `json_encode($ast)` сравнивается с ожидаемым `*.json`
  (инфраструктура `JsonSerializable` на узлах уже готова, файлы-эталоны
  добавляются по одному по мере покрытия синтаксиса).
- [ ] Расширить `tests/Ast/*Test.php` на все узлы (конструкторы, readonly-свойства).
- [ ] Адаптировать корпус тестов из `https://github.com/LalitMaganti/syntaqlite`
  (локальная копия — `./files/syntaqlite/tests/*`) — прогнать парсер, зафиксировать
  поддерживаемые/неподдерживаемые конструкции, добавить «канонические» примеры
  в `tests/Grammar/`. **Прогресс:** обработан приоритет 8 `amalg_tests/*`
  (13 файлов, см. `tests/External/PLAN.md` §5) — новых кейсов для грамматики
  не обнаружено: все SQL-фрагменты (`SELECT 1`, `SELECT a FROM t WHERE x = 1`,
  `CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL)`, `SELECT 1; SELECT 2`,
  `SELECT 42`) — дубликаты существующих тестов `SelectTest` / `DdlTest` /
  `FullScriptTest`; остальное — Perfetto-диалект (не SQLite 3.54) и C/C++-API
  (amalgamation/лимиты). Приоритеты 1–7 и 9–11 пока в статусе `⏳`.

---

## 5. Definition of Done (из `SKILL.md`)

Каждый этап завершается, только когда:

1. `vendor/bin/phplrt check grammar/sqlite.pp3 -v` → exit 0.
2. `git diff --exit-code -- src/Parser/SqliteParser.php` → пустой после `composer grammar:build`.
3. `src/` не импортирует `Compiler` / `LexerBuilder` / `ParserBuilder`.
4. `composer show phplrt/runtime` успешен (в проде).
5. Есть хотя бы один тест, создающий `new Inilim\SqliteParser\Parser\SqliteParser()` и
   проверяющий типизированное AST из репрезентативного входа.

Плюс собственный критерий библиотеки:

6. `composer stan` (phpstan level 8+) — чистый.
7. `composer tests` — зелёный.
8. `composer ci` — зелёный (единая точка входа, см. `.github/workflows/ci.yml`).

---

## 6. Открытые вопросы для пользователя

1. **`composer.json`**: ✅ **ЗАКРЫТ** — пользователь подтвердил переход на
   `phplrt/runtime` (require) + `phplrt/compiler` (require-dev).

2. **Обратный рендер AST → SQL** (`->toSql()`): нужен? Если да — выносится отдельным
   этапом после Этапа 8. **Ожидает решения.**

3. **Парсинг «частичного» SQL**: разрешать ли неполные выражения? **По умолчанию — строгий.**

4. **Уровень детализации типов**: различать `INTEGER` / `TEXT` / `REAL` / `BLOB` в
   `ColumnDefinition`, или хранить `type` строкой как пришло из SQL? **По умолчанию — строкой.**

5. **Версионирование SQLite**: ✅ **ЗАКРЫТ** — целевая версия диалекта **3.54.\***.

---

## 7. Milestones (когда что можно отдавать)

| Milestone      | После этапа | Что умеет                                                    |
|----------------|-------------|--------------------------------------------------------------|
| `v0.1.0-alpha` | 3           | Простой SELECT, без выражений сложнее `Literal`/`ColumnRef`   |
| `v0.2.0`       | 4           | Полные выражения, функции, CAST, CASE                         |
| `v0.3.0`       | 5           | JOIN, CTE, UNION, оконные функции                            |
| `v0.4.0`       | 6           | DML (INSERT/UPDATE/DELETE/UPSERT)                            |
| `v0.5.0`       | 7           | DDL (CREATE/ALTER/DROP)                                      |
| `v0.6.0`       | 8           | PRAGMA, ATTACH/DETACH, транзакции, SAVEPOINT, EXPLAIN        |
| `v1.0.0`       | 10          | Полное покрытие SQLite 3.54.* + CI + стабильный API          |

---

## 8. Внешний корпус тестов

Взять тесты из другого репозитория:
<https://github.com/LalitMaganti/syntaqlite/tree/main/tests>.

Локальная копия должна лежать в `./files/syntaqlite/tests/*`.

**Как использовать:**

1. **Скачать корпус** вручную (или скриптом) в `./files/syntaqlite/tests/`
   (каталог в `.gitignore`, эталоны не коммитятся).
2. **Написать вспомогательный тест-раннер** (data-provider), который читает
   `*.sql`-файлы из этого каталога и прогоняет их через `SqliteParser`.
3. **Разделить** входы на три категории:
   - «зелёные» — парсятся без исключений; перенести их в `tests/Grammar/` как
     регрессионные примеры;
   - «жёлтые» — валидный SQLite, но пока не покрыт грамматикой; оформить
     `@group known-fail` и вести список расширений грамматики;
   - «красные» — невалидный SQL (negative-tests); проверить, что парсер бросает
     исключение.
4. **Зафиксировать метрики**: `% успешных парсов` до/после — как индикатор прогресса
   при добавлении новых фич SQLite.

**Текущее состояние разбора корпуса.** Детальный журнал — `tests/External/PLAN.md`.
Обработан приоритет 8 (`amalg_tests/*`, 13 файлов). Итог: новых кейсов для грамматики
нет. Остальные приоритеты (1–7, 9–11) — `⏳ ожидает` присылки файлов пользователем.