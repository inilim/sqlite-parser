# Портированные тесты (syntaqlite → inilim/sqlite-parser)

Источник: `.sandbox/syntaqlite-main/syntaqlite-main/syntaqlite/tests`
(Rust-проект `syntaqlite`, парсер SQLite на C/Lemon + Rust API).

Здесь находятся тесты, которые удалось перенести на наш PHP-парсер.
Портирование делалось по *смыслу* проверок, а не построчно: у проектов
принципиально разный API (у нас — phplrt + типизированный AST, у них —
токенайзер/курсор с курсорной моделью и compile-time flags).

## Что портировано

| Файл-источник (Rust)                | Наш файл                              | Что проверяется |
|-------------------------------------|---------------------------------------|-----------------|
| `cursor_coexistence.rs`, `smoke.rs` (`parser_reuse`) | `ParserReuseTest.php` | переиспользование одного экземпляра парсера, независимость AST, восстановление после ошибки |
| `open_extension.rs`                 | `StatementVariantsTest.php`           | каждый SQL даёт ожидаемый типизированный узел, «unknown node» не появляется |
| `child_enumeration.rs`              | `ChildEnumerationTest.php`            | перечисление прямых детей узла (списки раскрываются), поиск/дамп структуры |
| `reject_invalid.rs`, `smoke.rs` (parse errors) | `RejectInvalidSqlTest.php` | заведомо невалидный SQL отвергается, валидные «соседи» принимаются |
| `low_level.rs` (границы инструкций, EXPLAIN, bare `;`) | `StatementBoundaryTest.php` | разбиение скрипта на инструкции, `EXPLAIN` не «протекает» на следующую инструкцию, пустые инструкции |
| `generated.rs` (`format_idempotent`), `formatter_keyword_case.rs` (частично) | `PrinterRoundTripTest.php` | `parse → print → parse → print`: результат парсится и печать идемпотентна |

## Что портировать нельзя (и почему)

| Файл-источник (Rust)                  | Причина |
|---------------------------------------|---------|
| `cflags.rs`, `cflag_apis.rs`, `cflag_validation.rs` | У нас нет compile-time flags (`SQLITE_OMIT_*`, `SQLITE_ENABLE_*`) и нет анализатора/каталога функций. Гейтинг ключевых слов и функций отсутствует как фича. |
| `multiversion.rs`                     | Нет переключения версии диалекта (`SqliteVersion`), целевой диалект зафиксирован: SQLite 3.54.*. |
| `embedded.rs`                         | Нет извлечения SQL из Python f-строк / TS template literals (`experimental-embedded`). |
| `fmt_comments.rs`                     | Наш `SqlPrinter` принципиально не сохраняет комментарии и исходное форматирование (см. «Known limitations» в корневом README). |
| `formatter_keyword_case.rs`           | Нет `FormatConfig`/`KeywordCase`: printer не конфигурируется по регистру ключевых слов. |
| `low_level.rs` (feed_token/IncrementalParseSession) | Нет инкрементального API «покормить токен» и нет публичного токенайзера; ближайший аналог — `SqliteParser::parse(ReadableInterface)`. |
| `smoke.rs` (`parse_error_recovery*`)  | phplrt-парсер бросает исключение и не восстанавливается на `;` — курсорной модели с `ParseOutcome::Err` у нас нет. Проверено частично: «парсер жив после исключения» в `ParserReuseTest`. |
| `cflag_validation.rs` (unknown function/table) | Нет семантического анализа; `ColumnResolver` — best-effort резолвер имён колонок, а не валидатор. |
| `reject_invalid.rs` (семантические проверки: `RETURNING` в триггере, `TEMP TRIGGER main.tr`, storage-keyword generated columns) | Это проверки уровня `build.c`/`trigger.c`, т.е. семантика. Наш парсер только синтаксический. |
| `multiversion.rs` / `reject_invalid.rs` (digit separators `1_000`) | Лексер не поддерживает разделители разрядов; версионный гейтинг отсутствует. |

## Осознанно не перенесённые «пограничные» кейсы

Эти проверки в Rust есть, но у нас их поведение не зафиксировано в README,
поэтому они не добавлены, чтобы не «зашивать» в CI случайное поведение:

- контекстно-зависимые ключевые слова как идентификаторы
  (`SELECT window FROM t`, `SELECT over FROM t`, `CREATE TABLE window (...)`) — `smoke.rs`, issue #108;
- комментарии как единственное содержимое скрипта (`/* c */`, `-- c`) — `smoke.rs`;
- висячие `ON`/`USING` без `JOIN` — `reject_invalid.rs`;
- `#N` register references — `reject_invalid.rs`.

Если decide поддержать/запретить это явно — добавляйте тесты вместе с
изменением грамматики (`grammar/sqlite.pp3` + `composer grammar:build`).