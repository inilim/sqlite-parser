# План разбора внешних тестов syntaqlite

> Рабочий документ. По нему ведём приём файлов из `files/syntaqlite/tests/`
> и решаем по каждому: полезен / не полезен / полезен частично.
>
> **Один шаг = один целый файл.** Файлы **не режем на части** — ты присылаешь
> файл целиком, я выношу вердикт. Если файл слишком большой и не влезает —
> он помечен `⚠️` в §3, и для него действует отдельное правило (§6): либо
> пробуем целиком, либо пропускаем. Никаких `part 1/2`, `a`/`b` и т.п.
>
> После каждого шага — точка синхронизации: обновление §5 и, если нужно,
> патчи `tests/Grammar/*Test.php` / `tests/External/*.php`.

---

## 1. Принцип

- **Не судим файл по имени.** Даже `amalg_tests/*.c` или `c_api/*.c` могут
  содержать SQL в строковых литералах. Поэтому проходим **все** файлы
  из `files/syntaqlite/tests/`, просто в разном порядке приоритета.
- **Один целый файл — один вердикт.** Никаких «наверное, полезно» — смотрим
  содержимое и решаем.
- **Извлекаем только то, что реально пригодится** нашему `SqliteParser`.
  Всё остальное (C-API, LSP-инфраструктура, форматтеры, lineage) — в графу
  «не полезно» с короткой причиной.
- **Файлы не режутся.** Ты присылаешь файл как есть. Если он >100 КБ и
  не влезает в контекст — действует §6 (пропуск / откладывание). Для
  **дубликатов ×30** (`bench_30x.sql`, `bench_30x_with_schema.sql`) —
  отдельное правило: пропускаем после обработки источника (см. §6).

---

## 2. Роли

**Пользователь:**

1. Берёт следующий шаг из §3 (строго по порядку, если не договорились иначе).
2. Присылает **один целый файл** в формате `<file path="...">...</file>`
   **с содержимым**.
3. Ждёт вердикта модели по этому файлу, и **только потом** присылает следующий.

**Модель (по каждому файлу):**

1. **Вердикт:** `полезно` / `не полезно` / `полезно частично`.
2. **Что нашлось:** конкретные SQL-фрагменты (строки / блоки), количество,
   одиночные или скрипт.
3. **Что делать:**
   - позитив → в `tests/Grammar/*Test.php` (указать файл и форму кейса);
   - негатив (`expectException`) → отдельный кейс;
   - дубликат → пропускаем с пометкой;
   - не SQL → в графу «пропущено».
4. **Апдейт эвристик** тест-класса в `tests/External/*.php` — если SQL
   в файле спрятан так, что текущие эвристики его не ловят (склейка из
   кусков, f-строки, конкатенация через `+`), предложить доработку extractor'а.

**После каждого файла:**

- модель ставит статус в §5 (один ряд — один файл);
- если что-то извлечено — прикладывает патч `tests/Grammar/*Test.php`;
- если нужна доработка extractor'а — прикладывает патч `tests/External/*.php`
  (или пишет «не нужно»);
- ждёт «дальше» от пользователя.

---

## 3. Очередь (плоский список, один шаг — один целый файл)

Порядок — по убыванию вероятности найти SQL. `⚠️` — файл >100 КБ
(см. §6).

### Приоритет 1 — `ast_diff_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 1 | `ast_diff_tests/__init__.py` | 135 B | |
| 2 | `ast_diff_tests/aggregate_raise.py` | 21 025 B | |
| 3 | `ast_diff_tests/cast_ptr_qnumber.py` | 15 140 B | |
| 4 | `ast_diff_tests/column_refs.py` | 9 273 B | |
| 5 | `ast_diff_tests/compound.py` | 24 563 B | |
| 6 | `ast_diff_tests/conditionals.py` | 29 619 B | |
| 7 | `ast_diff_tests/create_table.py` | 108 529 B | ⚠️ >100 КБ |
| 8 | `ast_diff_tests/cte.py` | 27 742 B | |
| 9 | `ast_diff_tests/dml.py` | 70 485 B | |
| 10 | `ast_diff_tests/functions.py` | 10 064 B | |
| 11 | `ast_diff_tests/misc_expr.py` | 8 200 B | |
| 12 | `ast_diff_tests/schema_ops.py` | 15 148 B | |
| 13 | `ast_diff_tests/select.py` | 33 786 B | |
| 14 | `ast_diff_tests/select_clauses.py` | 22 318 B | |
| 15 | `ast_diff_tests/table_source.py` | 42 537 B | |
| 16 | `ast_diff_tests/trigger.py` | 34 375 B | |
| 17 | `ast_diff_tests/utility_stmts.py` | 21 750 B | |
| 18 | `ast_diff_tests/values.py` | 4 232 B | |
| 19 | `ast_diff_tests/virtual_table.py` | 3 008 B | |
| 20 | `ast_diff_tests/window_functions.py` | 47 003 B | |

### Приоритет 2 — `semantic_diff_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 21 | `semantic_diff_tests/__init__.py` | 112 B | |
| 22 | `semantic_diff_tests/clauses.py` | 6 570 B | |
| 23 | `semantic_diff_tests/cte.py` | 3 663 B | |
| 24 | `semantic_diff_tests/ddl.py` | 31 591 B | |
| 25 | `semantic_diff_tests/dequoting.py` | 6 257 B | |
| 26 | `semantic_diff_tests/dml.py` | 5 810 B | |
| 27 | `semantic_diff_tests/functions.py` | 2 899 B | |
| 28 | `semantic_diff_tests/multi_statement.py` | 1 799 B | |
| 29 | `semantic_diff_tests/star.py` | 4 066 B | |
| 30 | `semantic_diff_tests/subqueries.py` | 12 632 B | |
| 31 | `semantic_diff_tests/unknown_column.py` | 4 036 B | negative-кейсы |
| 32 | `semantic_diff_tests/unknown_table.py` | 4 112 B | negative-кейсы |

### Приоритет 3 — `comparison/validator/cases/` (готовые `.sql`)

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 33 | `comparison/validator/cases/v01.sql` | 20 B | |
| 34 | `comparison/validator/cases/v02.sql` | 42 B | |
| 35 | `comparison/validator/cases/v03.sql` | 29 B | |
| 36 | `comparison/validator/cases/v04.sql` | 41 B | |
| 37 | `comparison/validator/cases/v05.sql` | 31 B | |
| 38 | `comparison/validator/cases/v06.sql` | 45 B | |
| 39 | `comparison/validator/cases/v07.sql` | 50 B | |
| 40 | `comparison/validator/cases/v08.sql` | 48 B | |
| 41 | `comparison/validator/cases/v09.sql` | 36 B | |
| 42 | `comparison/validator/cases/v10.sql` | 33 B | |

### Приоритет 4 — SQL-файлы в `comparison/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 43 | `comparison/schema.sql` | 2 279 B | DDL |
| 44 | `comparison/bench_statements.sql` | 3 547 B | |
| 45 | `comparison/test_statements.sql` | 8 101 B | |
| 46 | `comparison/validator/bench_with_schema.sql` | 3 939 B | |
| 47 | `comparison/parser/bench.sql` | 3 547 B | |
| 48 | `comparison/validator/bench_30x_with_schema.sql` | 106 802 B | ⚠️ >100 КБ, ×30 |
| 49 | `comparison/parser/bench_30x.sql` | 106 410 B | ⚠️ >100 КБ, ×30 |
| 50 | `comparison/lsp/schema.sql` | 153 B | |
| 51 | `comparison/lsp/test.sql` | 52 B | |
| 52 | `comparison/lsp/test_error.sql` | 53 B | negative |
| 53 | `lsp_tests/query.sql` | 24 B | |
| 54 | `lsp_tests/schema.sql` | 285 B | DDL |

### Приоритет 5 — `fmt_diff_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 55 | `fmt_diff_tests/__init__.py` | 113 B | |
| 56 | `fmt_diff_tests/comments.py` | 29 763 B | |
| 57 | `fmt_diff_tests/create_table.py` | 28 067 B | |
| 58 | `fmt_diff_tests/dml.py` | 13 628 B | |
| 59 | `fmt_diff_tests/misc.py` | 29 070 B | |
| 60 | `fmt_diff_tests/operator_precedence.py` | 25 785 B | |
| 61 | `fmt_diff_tests/schema_ops.py` | 6 174 B | |
| 62 | `fmt_diff_tests/select.py` | 15 481 B | |
| 63 | `fmt_diff_tests/utility_stmts.py` | 5 630 B | |
| 64 | `fmt_diff_tests/window.py` | 13 660 B | |

### Приоритет 6 — `perfetto_*` (диалект, может не парситься)

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 65 | `perfetto_fmt_diff_tests/__init__.py` | 112 B | |
| 66 | `perfetto_fmt_diff_tests/perfetto.py` | 23 116 B | |
| 67 | `perfetto_validation_diff_tests/__init__.py` | 112 B | |
| 68 | `perfetto_validation_diff_tests/perfetto.py` | 4 715 B | |

### Приоритет 7 — `lineage_diff_tests/`, `lsp_diff_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 69 | `lineage_diff_tests/__init__.py` | 112 B | |
| 70 | `lineage_diff_tests/ctas.py` | 2 515 B | |
| 71 | `lineage_diff_tests/cte.py` | 1 975 B | |
| 72 | `lineage_diff_tests/direct.py` | 2 583 B | |
| 73 | `lineage_diff_tests/errors.py` | 553 B | |
| 74 | `lineage_diff_tests/expressions.py` | 3 052 B | |
| 75 | `lineage_diff_tests/joins.py` | 2 708 B | |
| 76 | `lineage_diff_tests/star.py` | 3 439 B | |
| 77 | `lineage_diff_tests/union.py` | 1 388 B | |
| 78 | `lineage_diff_tests/view.py` | 1 623 B | |
| 79 | `lsp_diff_tests/__init__.py` | 112 B | |
| 80 | `lsp_diff_tests/completion.py` | 10 760 B | |
| 81 | `lsp_diff_tests/definition.py` | 4 374 B | |
| 82 | `lsp_diff_tests/diagnostics.py` | 3 250 B | |
| 83 | `lsp_diff_tests/hover.py` | 1 217 B | |
| 84 | `lsp_diff_tests/references.py` | 5 421 B | |
| 85 | `lsp_diff_tests/rename.py` | 4 970 B | |
| 86 | `lsp_diff_tests/shell.py` | 5 572 B | |

### Приоритет 8 — `amalg_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 87 | `amalg_tests/__init__.py` | 0 B | |
| 88 | `amalg_tests/perfetto.py` | 27 372 B | |
| 89 | `amalg_tests/perfetto_dialect_only.py` | 2 518 B | |
| 90 | `amalg_tests/probe_create.c` | 879 B | |
| 91 | `amalg_tests/probe_with_dialect.c` | 769 B | |
| 92 | `amalg_tests/sqlite.py` | 6 489 B | |
| 93 | `amalg_tests/sqlite_full.py` | 4 093 B | |
| 94 | `amalg_tests/sqlite_ndebug.py` | 1 389 B | |
| 95 | `amalg_tests/sqlite_omit_macros.py` | 4 129 B | |
| 96 | `amalg_tests/sqlite_omit_runtime.py` | 4 253 B | |
| 97 | `amalg_tests/sqlite_runtime_only.py` | 3 659 B | |
| 98 | `amalg_tests/test_ast.c` | 3 884 B | |
| 99 | `amalg_tests/test_strict_warnings.cpp` | 1 075 B | |

### Приоритет 9 — `c_api/`, `c_api_tests/`

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 100 | `c_api/api-manifest.json` | 166 091 B | ⚠️ >100 КБ, JSON |
| 101 | `c_api/parser_library_smoke.c` | 1 067 B | |
| 102 | `c_api/static_library_smoke.c` | 1 168 B | |
| 103 | `c_api_tests/__init__.py` | 0 B | |
| 104 | `c_api_tests/formatter.py` | 4 289 B | |
| 105 | `c_api_tests/formatter_driver.c` | 4 596 B | |
| 106 | `c_api_tests/parser.py` | 27 139 B | |
| 107 | `c_api_tests/parser_driver.c` | 17 856 B | |
| 108 | `c_api_tests/validator.py` | 9 210 B | |
| 109 | `c_api_tests/validator_driver.c` | 16 330 B | |

### Приоритет 10 — прочее `comparison/` (не SQL)

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 110 | `comparison/formatter/_fmt_sqlglot.py` | 157 B | |
| 111 | `comparison/lsp/_lsp_test.js` | 9 409 B | |
| 112 | `comparison/parser/src/lemon_rs.rs` | 1 084 B | |
| 113 | `comparison/parser/src/sqlparser_rs.rs` | 874 B | |
| 114 | `comparison/parser/_parse_cst.js` | 131 B | |
| 115 | `comparison/parser/_parse_node.js` | 159 B | |
| 116 | `comparison/parser/_parse_sqlglot.py` | 82 B | |
| 117 | `comparison/parser/Cargo.toml` | 287 B | |
| 118 | `comparison/parser/Cargo.lock` | 9 841 B | |
| 119 | `comparison/validator/_lsp_validate.js` | 4 719 B | |
| 120 | `comparison/README.md` | 1 902 B | |
| 121 | `comparison/pyproject.toml` | 237 B | |
| 122 | `comparison/package.json` | 542 B | |
| 123 | `comparison/package-lock.json` | 316 244 B | ⚠️⚠️ >200 КБ, lock-файл |
| 124 | `comparison/uv.lock` | 42 730 B | lock-файл |
| 125 | Мелкие конфиги `comparison/`: `.prettierrc`, `.python-version`, `.sqlfluff`, `.sqruff` | ~206 B | одним фрагментом |

### Приоритет 11 — остальное

| # | Файл | Размер | Заметка |
|---|---|---|---|
| 126 | `benches/Cargo.toml` | 560 B | |
| 127 | `benches/benches/main.rs` | 8 848 B | |
| 128 | `public-api/syntaqlite-buildtools.txt` | 15 852 B | |
| 129 | `public-api/syntaqlite.txt` | 40 159 B | |
| 130 | `public-api/syntaqlite-syntax.txt` | 290 823 B | ⚠️⚠️ >200 КБ |
| 131 | `token_preservation/baseline.json` | 3 B | |
| 132 | `upstream/csrc/tclsyntaqlite.c` | 14 535 B | |
| 133 | `upstream/tcl/tester.tcl` | 6 066 B | |
| 134 | `upstream_baselines/parse_acceptance.darwin.json` | 6 614 B | |
| 135 | `upstream_baselines/parse_acceptance.linux.json` | 5 722 B | |
| 136 | `tests/__init__.py` (корень) | 112 B | |

> **Примечание.** Если какого-то файла в реальной папке не окажется (или
> наоборот, найдётся файл, которого нет в списке) — просто пропускаем /
> добавляем в конец очереди. Список — ориентир, а не догма.

---

## 4. Чек-лист по каждому файлу

- **Извлекаю SQL-литералы** (`'...'`, `"..."`, `"""..."""`), смотрю, что из
  них — SQL (первое слово из keyword-списка).
- **Отделяю одиночные запросы от скриптов** (`;` внутри — если несколько,
  это уже скрипт).
- **Проверяю negative-кейсы:** если литерал похож на «битый» SQL — это может
  быть `expectException`-тест. Ценно.
- **Сравниваю с уже перенесённым** в `tests/Grammar/` — если 100% дубликат,
  пропускаю.
- **Смотрю на структуру** — если в Python-коде есть `parse("SELECT ...")` +
  `assert`-ожидание, могу вытащить не только SQL, но и ожидаемую структуру
  (полезно для snapshot-тестов).

---

## 5. Прогресс разбора

Заполняется по одному файлу. Статусы: `✅ полезно` / `⚠️ частично` /
`❌ не полезно` / `⏳ ожидает` / `⏭️ пропущено`.

> **Порядок.** Формально §3 предписывает строго последовательный проход.
> Фактически пользователь прислал пакет `amalg_tests/*` (приоритет 8) первым —
> обработан вне очереди. Приоритеты 1–7 остаются в статусе `⏳`.

### Приоритет 1 — `ast_diff_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 1 | `__init__.py` | ⏳ | — |
| 2 | `aggregate_raise.py` | ⏳ | — |
| 3 | `cast_ptr_qnumber.py` | ⏳ | — |
| 4 | `column_refs.py` | ⏳ | — |
| 5 | `compound.py` | ⏳ | — |
| 6 | `conditionals.py` | ⏳ | — |
| 7 | `create_table.py` ⚠️ | ⏳ | — |
| 8 | `cte.py` | ⏳ | — |
| 9 | `dml.py` | ⏳ | — |
| 10 | `functions.py` | ⏳ | — |
| 11 | `misc_expr.py` | ⏳ | — |
| 12 | `schema_ops.py` | ⏳ | — |
| 13 | `select.py` | ⏳ | — |
| 14 | `select_clauses.py` | ⏳ | — |
| 15 | `table_source.py` | ⏳ | — |
| 16 | `trigger.py` | ⏳ | — |
| 17 | `utility_stmts.py` | ⏳ | — |
| 18 | `values.py` | ⏳ | — |
| 19 | `virtual_table.py` | ⏳ | — |
| 20 | `window_functions.py` | ⏳ | — |

### Приоритет 2 — `semantic_diff_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 21 | `__init__.py` | ⏳ | — |
| 22 | `clauses.py` | ⏳ | — |
| 23 | `cte.py` | ⏳ | — |
| 24 | `ddl.py` | ⏳ | — |
| 25 | `dequoting.py` | ⏳ | — |
| 26 | `dml.py` | ⏳ | — |
| 27 | `functions.py` | ⏳ | — |
| 28 | `multi_statement.py` | ⏳ | — |
| 29 | `star.py` | ⏳ | — |
| 30 | `subqueries.py` | ⏳ | — |
| 31 | `unknown_column.py` | ⏳ | — |
| 32 | `unknown_table.py` | ⏳ | — |

### Приоритеты 3–4 — SQL-файлы `comparison/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 33–42 | `validator/cases/v01..v10.sql` | ⏳ | — |
| 43 | `schema.sql` | ⏳ | — |
| 44 | `bench_statements.sql` | ⏳ | — |
| 45 | `test_statements.sql` | ⏳ | — |
| 46 | `validator/bench_with_schema.sql` | ⏳ | — |
| 47 | `parser/bench.sql` | ⏳ | — |
| 48 | `validator/bench_30x_with_schema.sql` ⚠️ | ⏳ | — |
| 49 | `parser/bench_30x.sql` ⚠️ | ⏳ | — |
| 50 | `lsp/schema.sql` | ⏳ | — |
| 51 | `lsp/test.sql` | ⏳ | — |
| 52 | `lsp/test_error.sql` | ⏳ | — |
| 53 | `lsp_tests/query.sql` | ⏳ | — |
| 54 | `lsp_tests/schema.sql` | ⏳ | — |

### Приоритет 5 — `fmt_diff_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 55–64 | `fmt_diff_tests/*` (10 файлов) | ⏳ | — |

### Приоритет 6 — `perfetto_*`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 65–68 | `perfetto_*/*` (4 файла) | ⏳ | — |

### Приоритет 7 — `lineage_diff_tests/`, `lsp_diff_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 69–78 | `lineage_diff_tests/*` (10 файлов) | ⏳ | — |
| 79–86 | `lsp_diff_tests/*` (8 файлов) | ⏳ | — |

### Приоритет 8 — `amalg_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 87 | `amalg_tests/__init__.py` | ⏭️ | Пустой файл (0 B). Нечего извлекать. |
| 88 | `amalg_tests/perfetto.py` | ❌ | Perfetto-диалект (расширение SQLite, не SQLite 3.54). SQLite-кейсы внутри: `SELECT 1`, `CREATE TABLE t (id INTEGER)` — дубликаты существующих тестов. Остальное — Perfetto-синтаксис, который не парсится нашим SQLite-парсером: `CREATE PERFETTO TABLE/VIEW/FUNCTION/INDEX/MACRO`, `INCLUDE PERFETTO MODULE`, `DROP PERFETTO INDEX`, pipelines `FROM t \|> DROP a`, node expander. Не наш диалект. |
| 89 | `amalg_tests/perfetto_dialect_only.py` | ❌ | Тот же Perfetto-диалект. `SELECT 1` (дубликат) и `CREATE PERFETTO TABLE foo AS SELECT 1` (не-SQLite). |
| 90 | `amalg_tests/probe_create.c` | ❌ | C-API probe (`syntaqlite_parser_create_<dialect>`, `syntaqlite_tokenizer_create_<dialect>`). SQL-литералов нет. |
| 91 | `amalg_tests/probe_with_dialect.c` | ❌ | C-API probe (`*_with_dialect`). SQL-литералов нет. |
| 92 | `amalg_tests/sqlite.py` | ⚠️ частично | SQL есть, но всё — дубликаты: `SELECT 1` (SelectTest), `SELECT a FROM t WHERE x = 1` (SelectTest, `SELECT id, name FROM users WHERE id = 1`), `CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL)` (DdlTest, `... PRIMARY KEY AUTOINCREMENT, ...` — надмножество), `SELECT 1; SELECT 2` (FullScriptTest). Новых кейсов нет. |
| 93 | `amalg_tests/sqlite_full.py` | ❌ | Подмножество `sqlite.py` (`SELECT 1`, `SELECT a FROM t WHERE x = 1`, `SELECT 1; SELECT 2`) — дубликаты. |
| 94 | `amalg_tests/sqlite_ndebug.py` | ❌ | Один кейс `SELECT 1` — дубликат. |
| 95 | `amalg_tests/sqlite_omit_macros.py` | ❌ | Те же 3 кейса, что в `sqlite.py` — дубликаты. |
| 96 | `amalg_tests/sqlite_omit_runtime.py` | ❌ | Те же 3 кейса, что в `sqlite.py` — дубликаты. |
| 97 | `amalg_tests/sqlite_runtime_only.py` | ❌ | `SELECT 42` (отличается от `SELECT 1` только литералом) и `CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL)` — дубликат DdlTest. |
| 98 | `amalg_tests/test_ast.c` | ❌ | C-драйвер amalgamation-тестов. SQL в литералах отсутствует (подаётся из stdin). |
| 99 | `amalg_tests/test_strict_warnings.cpp` | ❌ | Compile-only C++ тест. Единственный `SELECT 1` — дубликат. |

**Итог по приоритету 8.** Новых SQL-кейсов для грамматики SQLite **не обнаружено**.
Все SQL-фрагменты — дубликаты уже существующих тестов (`SelectTest`, `DdlTest`,
`FullScriptTest`); остальное — Perfetto-диалект (не SQLite) и C/C++-API-обвязка.
Патчей в `tests/Grammar/*Test.php` **не требуется**, доработок extractor'а
**не требуется**.

### Приоритет 9 — `c_api/`, `c_api_tests/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 100 | `api-manifest.json` ⚠️ | ⏳ | — |
| 101–109 | `c_api/*`, `c_api_tests/*` (9 файлов) | ⏳ | — |

### Приоритет 10 — прочее `comparison/`

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 110–122 | `comparison/*` мелкие (13 файлов) | ⏳ | — |
| 123 | `package-lock.json` ⚠️⚠️ | ⏳ | — |
| 124 | `uv.lock` | ⏳ | — |
| 125 | Мелкие конфиги `comparison/` | ⏳ | — |

### Приоритет 11 — остальное

| # | Файл | Статус | Что нашли / куда |
|---|---|---|---|
| 126–136 | `benches/*`, `public-api/*`, `token_preservation/*`, `upstream/*`, `upstream_baselines/*`, `tests/__init__.py` | ⏳ | — |
| 130 | `public-api/syntaqlite-syntax.txt` ⚠️⚠️ | ⏳ | — |

---

## 6. Правило для больших файлов (⚠️ / ⚠️⚠️)

Ты присылаешь файл целиком. Если он не влезает — терять контекст не хочется,
поэтому:

**`⚠️` — файл 100–200 КБ** (шаги 7, 48, 49, 100):

- Пробуем прислать **целиком**. Часто влезает.
- Если не влезает — пропускаем и ставим `⏭️ пропущено`, ничего не теряем
  критического (см. ниже).
- Для **`bench_30x.sql`** и **`bench_30x_with_schema.sql`** — это буквально
  `bench.sql` / `bench_with_schema.sql`, повторённые 30 раз. Правило:
  **сначала обрабатываем источник** (шаги 46 и 47), а `bench_30x*`
  **пропускаем** (`⏭️`) — там нет новой информации, только повторы.
  Помечаю это в §5 при обработке.
- **`create_table.py`** (108 КБ) и **`api-manifest.json`** (166 КБ) —
  реально пробуем, потому что могут содержать важное. `api-manifest.json`
  — JSON, возможно, без SQL; если не влезет — пропускаем.

**`⚠️⚠️` — файл > 200 КБ** (шаги 123, 130):

- `package-lock.json` (316 КБ) — npm lock, почти наверняка не SQL. Ставим
  `⏭️ пропущено` без попытки.
- `public-api/syntaqlite-syntax.txt` (290 КБ) — дамп публичного API, почти
  наверняка не SQL. Ставим `⏭️ пропущено`.

Итого реально проблемные файлы: только `create_table.py` (108 КБ) и
`api-manifest.json` (166 КБ). Остальные либо дубликаты ×30, либо
не-SQL.

---

## 7. Что нужно от пользователя

1. Подтвердить порядок шагов §3.
2. Присылать **один целый файл** за раз, без нарезки.
3. Шаги `⚠️` / `⚠️⚠️` — см. §6: там отдельное поведение (пропуск,
   приоритет источника, попытка/пропуск).
4. Начать предлагаю с шага 1: `ast_diff_tests/__init__.py` (135 байт),
   затем шаг 2: `ast_diff_tests/aggregate_raise.py`.