# План разработки библиотеки `inilim/sqlite-parser`

> Поэтапный план создания PHP-библиотеки, превращающей SQL-текст SQLite в типизированное AST
> на основе `phplrt`. Итеративный: каждый этап завершается работающей грамматикой, коммитом
> сгенерированного парсера и тестами на соответствующий участок синтаксиса.

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
   Сейчас в `composer.json` стоит `phplrt/phplrt` (метапакет) — **пересмотреть в Этапе 0**
   (см. «Открытые вопросы»).
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
```

---

## 4. Этапы

### Этап 0 — Инфраструктура (0.5 дня)

**Цель:** чистая основа, окружение готово к кодогенерации.

- [ ] Привести `composer.json` в порядок:
  - `require`: `php: ^8.4`, `inilim/tools: ^0.1`, `phplrt/runtime: ^4.2`;
  - `require-dev`: `phplrt/compiler: ^4.2`, `phpunit/phpunit: ^9.6`, `phpstan/phpstan: ^2.2`;
  - добавить скрипты `grammar:check` и `grammar:build`.
- [ ] Создать каталоги `grammar/`, `src/Ast/`, `src/Parser/`, `tests/`.
- [ ] `phpunit.xml.dist`, `phpstan.neon.dist`.
- [ ] `README.md` — короткое описание + пример API.
- [ ] `.gitignore` (дополнить `vendor/`, `.phpunit.result.cache`, `var/`).

**Готово когда:** `composer install` чистый, `composer tests` запускается (даже с 0 тестов),
`vendor/bin/phplrt --version` работает.

> ⚠️ Перед правкой `composer.json` — согласовать с пользователем: смена `phplrt/phplrt` →
> `phplrt/runtime` + `phplrt/compiler` требует `composer require` / `composer remove`.

---

### Этап 1 — Фундамент AST (0.5 дня)

**Цель:** каркас типов, от которого будут зависеть все последующие этапы.

- [ ] `src/Ast/Node.php` — абстрактный класс с `public readonly array $attributes` (для отладки)
  и методом `jsonSerialize()` (реализация `JsonSerializable` для тестов).
- [ ] `src/Ast/Script.php` — `public readonly array $statements`.
- [ ] `src/Ast/Statement.php` — абстрактный.
- [ ] `src/Ast/Expression.php` — абстрактный.
- [ ] Заглушки конкретных типов появительных по мере этапов (пока пустые).
- [ ] Первый тест `tests/Ast/ScriptTest.php` — конструктор работает.

**Готово когда:** `phpstan analyse` чистый, базовые тесты зелёные.

---

### Этап 2 — Лексер и токены (1 день)

**Цель:** полный набор токенов SQLite в `grammar/tokens.pp3`.

- [ ] Ключевые слова (`SELECT`, `FROM`, `WHERE`, `CREATE`, `TABLE`, ...) —
  **регистронезависимо** (`RegexModifier::Caseless` в builder-API, флаг `i` в `.pp3`).
  Порядок объявления: многословные ключевые слова (`ORDER BY`) — **до** однословных
  (`ORDER`, `BY`); ключевые слова — до `T_IDENT`.
- [ ] Идентификаторы:
  - `T_IDENT` — `[A-Za-z_][A-Za-z0-9_]*`;
  - `T_QUOTED_IDENT` — `"..."`, `` `...` ``, `[...]`;
- [ ] Литералы:
  - `T_INT` (десятичный, `0x...`, `0b...`), `T_FLOAT`;
  - `T_STRING` — `'...'` с `''` как экранирование;
  - `T_BLOB` — `X'...'`;
  - `T_NULL`, `T_TRUE`, `T_FALSE`;
- [ ] Операторы: `+ - * / %`, `= <> != < <= > >=`, `||`, `& | ~ << >>`,
  `AND OR NOT IS IN LIKE GLOB BETWEEN EXISTS`.
- [ ] Пунктуация: `( ) , . ; :`.
- [ ] `%skip T_WHITESPACE \s++`, `%skip T_COMMENT --[^\n]*`, `%skip T_COMMENT_BLOCK /\*.*?\*/s`
  (многострочный комментарий — не-DotAll режим, `.*?` ленивый).
- [ ] `grammar/statement.pp3` — скелет: `Script : Statement() (::T_SEMICOLON:: Statement())* ;`,
  `Statement : ...` (пока с одной заглушкой `Empty`).
- [ ] `grammar/sqlite.pp3` — `%pragma root Script`, `%include tokens`, `%include statement`.

**Проверки:**
- [ ] `vendor/bin/phplrt check grammar/sqlite.pp3 -v` → exit 0, `Rules: Loaded/After` не схлопывается до 1.
- [ ] Парсер возвращает `Script` без ошибок на пустой строке и на `;`.

**Готово когда:** `check` зелёный, базовый парсер генерируется и возвращает `Script([])`
для пустого ввода и `Script([Empty])` для `;`.

---

### Этап 3 — SELECT (MVP) (1–2 дня)

**Цель:** «скелет» библиотеки — можно спарсить простой SELECT.

- [ ] `grammar/select.pp3`:
  - `SelectStatement : ::T_SELECT:: SelectList() ::T_FROM:: TableRef() SelectTail()? ;`
  - `SelectList : (ResultColumn() (::T_COMMA:: ResultColumn())*) ;`
  - `ResultColumn : Expression() (::T_AS:: ::T_IDENT::)? | ::T_STAR:: ;`
  - `TableRef : TableName() (::T_AS:: ::T_IDENT::)? ;`
  - `SelectTail` (пока): `WHERE`, `ORDER BY`, `LIMIT` / `OFFSET`.
- [ ] AST-узлы: `SelectStatement`, `ResultColumn`, `TableReference`, `OrderBy`.
- [ ] Минимальные выражения (см. Этап 4 будет расширять):
  - `Literal` (`T_INT`, `T_FLOAT`, `T_STRING`, `T_NULL`), `ColumnReference`, `Star`.
- [ ] Reducer'ы в `.pp3` возвращают `new Node(...)` (с `--use`, если понадобится FQCN — см. Этап 4).

**Проверки:**
- [ ] Тест: `SELECT 1` → `Script([SelectStatement(columns=[Literal(1)])])`.
- [ ] Тест: `SELECT * FROM users` → корректное AST.
- [ ] Тест: `SELECT id, name FROM users WHERE id = 1` → AST с `Where`.

**Готово когда:** эти три сценария проходят через `new Inilim\SqliteParser\Parser\SqliteParser()`.

---

### Этап 4 — Выражения (2 дня)

**Цель:** полноценная грамматика выражений SQLite.

- [ ] `grammar/expression.pp3`:
  - `Expression : OrExpr() ;`
  - `OrExpr : AndExpr() (::T_OR:: AndExpr())* ;`
  - `AndExpr : NotExpr() (::T_AND:: NotExpr())* ;`
  - `NotExpr : ::T_NOT:: NotExpr() | Comparison() ;`
  - `Comparison : Additive() (('=' | '<>' | '!=' | '<' | '<=' | '>' | '>=') Additive()
                        | ::T_IS:: ::T_NULL:: | ::T_IS:: ::T_NOT:: ::T_NULL::
                        | ::T_LIKE:: Additive() | ::T_GLOB:: Additive()
                        | ::T_IN:: '(' ExprList()? ')'
                        | ::T_BETWEEN:: Additive() ::T_AND:: Additive())* ;`
  - `Additive : Multiplicative() (('+' | '-') Multiplicative())* ;`
  - `Multiplicative : Unary() (('*' | '/' | '%') Unary())* ;`
  - `Unary : ('+' | '-' | '~') Unary() | Primary() ;`
  - `Primary : '(' Expression() ')' | Literal() | ColumnReference()
              | FunctionCall() | CaseExpression() | CastExpression() | Subquery() ;`
- [ ] Узлы: `Literal`, `ColumnReference`, `BinaryOperation`, `UnaryOperation`,
  `FunctionCall`, `CaseExpression`, `CastExpression`, `BetweenExpression`,
  `InExpression`, `LikeExpression`, `IsNullExpression`, `Subquery`.
- [ ] `FunctionCall`: `name '(' (Expression (',' Expression)*)? ')'`,
  поддержка `DISTINCT` внутри агрегатов.
- [ ] `CAST(x AS type)` — отдельная ветка.
- [ ] **Трюк с flattening**: каждый вложенный узел возвращает объект (не голый массив),
  иначе `$children` родителя схлопнется (см. `SKILL.md` → «gotcha»).
- [ ] При `compile` использовать `--use "Inilim\SqliteParser\Ast\Expression\..."` (или через alias).

**Проверки:**
- [ ] Приоритеты: `1 + 2 * 3` → `Add(1, Mul(2, 3))`.
- [ ] Скобки: `(1 + 2) * 3` → `Mul(Add(1, 2), 3)`.
- [ ] `a = 1 AND b <> 2` → `And(Eq(a,1), Ne(b,2))`.
- [ ] `CAST(x AS INTEGER)` → `CastExpression`.
- [ ] `COUNT(DISTINCT x)` → `FunctionCall('COUNT', [x], distinct: true)`.

**Готово когда:** ~10 тестов на приоритеты, ассоциативность, функции — зелёные.

---

### Этап 5 — Продвинутый SELECT (2 дня)

**Цель:** JOIN, CTE, UNION, оконные функции.

- [ ] `FROM` с несколькими источниками и `JOIN ... ON` / `USING`:
  - `FromClause : TableRef() (JoinClause())* ;`
  - `JoinClause : JoinType? ::T_JOIN:: TableRef() (::T_ON:: Expression() | ::T_USING:: '(' IdentList ')' )? ;`
- [ ] `GROUP BY` + `HAVING`.
- [ ] `DISTINCT` / `ALL` в `SELECT`.
- [ ] `UNION [ALL]` / `INTERSECT` / `EXCEPT` — многоуровневая композиция (левая рекурсия запрещена,
  реализовать как `QueryTerm (op QueryTerm)*`).
- [ ] `WITH [RECURSIVE] name AS (subquery)` — CTE.
- [ ] Оконные функции: `OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE ...)`.
- [ ] AST: `JoinClause`, `GroupBy`, `Having`, `CompoundSelect`, `CommonTableExpression`,
  `WindowSpecification`.
- [ ] `Subquery` в `FROM` (derived table).

**Проверки:**
- [ ] `SELECT * FROM a JOIN b ON a.id = b.id` → `SelectStatement` с `JoinClause`.
- [ ] `WITH c AS (SELECT 1) SELECT * FROM c` → `SelectStatement` с `ctes`.
- [ ] `SELECT 1 UNION SELECT 2` → `CompoundSelect(union, [Select, Select])`.
- [ ] `SUM(x) OVER (PARTITION BY g)` → `WindowFunction`.

---

### Этап 6 — DML: INSERT / UPDATE / DELETE / UPSERT (1–2 дня)

**Цель:** модификация данных.

- [ ] `INSERT [OR REPLACE|IGNORE|ABORT|FAIL|ROLLBACK] INTO tbl [(cols)] VALUES (...),(...)
  | SELECT ... | DEFAULT VALUES`
- [ ] `INSERT ... ON CONFLICT (...) DO UPDATE SET ... [WHERE ...]`
  (и `DO NOTHING`).
- [ ] `UPDATE [OR ...] tbl SET col = expr, ... [WHERE ...] [RETURNING ...]`
- [ ] `DELETE FROM tbl [WHERE ...] [RETURNING ...]`
- [ ] AST: `InsertStatement`, `UpdateStatement`, `DeleteStatement`, `UpsertClause`,
  `ReturningClause`, `Assignment`.

**Проверки:**
- [ ] `INSERT INTO t (a, b) VALUES (1, 2)` → `InsertStatement`.
- [ ] `INSERT INTO t VALUES (1) ON CONFLICT (a) DO NOTHING` → upsert-ветка.
- [ ] `UPDATE t SET a = 1 WHERE b = 2 RETURNING a`.

---

### Этап 7 — DDL: CREATE / ALTER / DROP (2 дня)

**Цель:** описание схемы.

- [ ] `CREATE TABLE [IF NOT EXISTS] tbl (column_defs..., table_constraints...)`
  - `column_def`: `name type [PRIMARY KEY [AUTOINCREMENT]] [NOT NULL] [UNIQUE] [DEFAULT expr] [CHECK(...)] [REFERENCES ...]`
  - `table_constraint`: `PRIMARY KEY (...)`, `UNIQUE (...)`, `FOREIGN KEY (...) REFERENCES ...`,
    `CHECK (...)`
  - `CREATE TABLE ... AS SELECT ...`
  - `WITHOUT ROWID`, `STRICT`
- [ ] `CREATE [UNIQUE] INDEX [IF NOT EXISTS] name ON tbl (cols) [WHERE ...]`
- [ ] `CREATE [TEMP] VIEW [IF NOT EXISTS] name AS SELECT ...`
- [ ] `CREATE TRIGGER ... BEFORE/AFTER/INSTEAD OF INSERT/UPDATE/DELETE ON tbl ...`
- [ ] `ALTER TABLE tbl RENAME TO ... | ADD COLUMN ... | DROP COLUMN ... | RENAME COLUMN ... TO ...`
- [ ] `DROP TABLE|INDEX|VIEW|TRIGGER [IF EXISTS] name`
- [ ] AST: `CreateTableStatement`, `ColumnDefinition`, `TableConstraint`, `ForeignKeyClause`,
  `CreateIndexStatement`, `CreateViewStatement`, `CreateTriggerStatement`,
  `AlterTableStatement`, `DropStatement`.

**Проверки:**
- [ ] `CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)`.
- [ ] `CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b))`.
- [ ] `CREATE INDEX idx ON t (a, b) WHERE a > 0`.

---

### Этап 8 — Утилитарные инструкции (0.5–1 день)

- [ ] `PRAGMA name [(value)]` / `PRAGMA name = value`
- [ ] `ATTACH [DATABASE] expr AS ident` / `DETACH [DATABASE] ident`
- [ ] `BEGIN [DEFERRED|IMMEDIATE|EXCLUSIVE] [TRANSACTION]` / `COMMIT` / `END` / `ROLLBACK`
- [ ] `SAVEPOINT name` / `RELEASE [SAVEPOINT] name` / `ROLLBACK TO [SAVEPOINT] name`
- [ ] `EXPLAIN [QUERY PLAN] statement`
- [ ] AST: `PragmaStatement`, `AttachStatement`, `DetachStatement`, `TransactionStatement`,
  `SavepointStatement`, `ExplainStatement`.

---

### Этап 9 — Генерация и упаковка (0.5 дня)

- [ ] Скрипты в `composer.json`:
  - `grammar:check`: `vendor/bin/phplrt check grammar/sqlite.pp3 -v`
  - `grammar:build`: `vendor/bin/phplrt compile grammar/sqlite.pp3 src/Parser/SqliteParser.php --class=SqliteParser --namespace='Inilim\SqliteParser\Parser' --use='Inilim\SqliteParser\Ast\...'` (со всеми `--use` для узлов).
- [ ] Убедиться, что `src/` **не** ссылается на `LexerBuilder`, `ParserBuilder`, `Compiler`.
- [ ] `git diff --exit-code -- src/Parser/SqliteParser.php` — пустой после regenerate.
- [ ] README: секции «Install», «Usage», «Supported SQL», «Development».

**Готово когда:** все критерии Definition of Done ниже — зелёные.

---

### Этап 10 — Тесты и CI (постоянно)

- [ ] `tests/Grammar/*Test.php` — один файл на группу синтаксиса (SELECT, DML, DDL, ...).
- [ ] `tests/Ast/*Test.php` — тесты на отдельные узлы (конструкторы, геттеры).
- [ ] Snapshot-тесты: `json_encode($ast)` сравнивается с ожидаемым `*.json`.
- [ ] `tests/Parser/SqliteParserTest.php` — **инстанцирует именно `new SqliteParser()`** (сгенерированный), не билдеры.
- [ ] GitHub Actions: matrix PHP 8.4, `composer install`, `grammar:check`, `tests`, `stan`.
- [ ] Проверить, что `grammar:build` в CI также прогоняется и сравнивает `git diff` (ловит устаревший сгенерированный парсер).

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

---

## 6. Открытые вопросы для пользователя

1. **`composer.json`**: сейчас в `require` стоит `phplrt/phplrt` (метапакет, тянет
   `phplrt/compiler` + laminas + symfony + twig в прод). Заменить на:
   - `phplrt/runtime` в `require`;
   - `phplrt/compiler` в `require-dev`.
   Требует `composer require phplrt/runtime` и `composer remove phplrt/phplrt` + `composer require --dev phplrt/compiler`.
   **Даю добро?**

2. **Обратный рендер AST → SQL** (`->toSql()`): нужен? Если да — выносится отдельным
   этапом после Этапа 8.

3. **Парсинг «частичного» SQL**: разрешать ли неполные выражения (например, `SELECT id FROM`
   без завершения) или всегда строгий PEG (весь вход должен быть съеден)? По умолчанию — строгий.

4. **Уровень детализации типов**: различать `INTEGER` / `TEXT` / `REAL` / `BLOB` в
   `ColumnDefinition`, или хранить `type` строкой как пришло из SQL? По умолчанию — строкой
   (упрощает).

5. **Версионирование SQLite**: целевая версия диалекта — 3.45+ (актуальный LTS)? Влияет на
   наличие `RETURNING`, `STRICT`, `ON CONFLICT DO UPDATE`.

---

## 7. Milestones (когда что можно отдавать)

| Milestone      | После этапа | Что умеет                                                    |
|----------------|-------------|--------------------------------------------------------------|
| `v0.1.0-alpha` | 3           | Простой SELECT, без выражений сложнее `Literal`/`ColumnRef`   |
| `v0.2.0`       | 4           | Полные выражения, функции, CAST, CASE                         |
| `v0.3.0`       | 5           | JOIN, CTE, UNION, оконные функции                            |
| `v0.4.0`       | 6           | DML (INSERT/UPDATE/DELETE/UPSERT)                            |
| `v0.5.0`       | 7           | DDL (CREATE/ALTER/DROP)                                      |
| `v1.0.0`       | 10          | Полное покрытие SQLite + CI + стабильный API                 |