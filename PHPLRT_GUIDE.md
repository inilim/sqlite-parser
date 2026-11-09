# Руководство по phplrt 4.2

> Практическая шпаргалка по библиотеке [`phplrt`](https://phplrt.org) версии **4.2** —
> на основе официальной документации и опыта интеграции в `inilim/sqlite-parser`.
> Все примеры проверены на PHP 8.4.

---

## 1. Что такое phplrt

`phplrt` (PHP Language Recognition Tool) — набор библиотек для разбора текста:
лексер, парсер, компилятор грамматик, работа с ошибками. Позволяет описать язык
в декларативном файле `.pp3` и получить готовый парсер.

**Ключевая идея:** грамматика пишется один раз в `.pp3`, компилируется в PHP-класс
(или используется в runtime через `getParser()`), а в продакшене подключается
только `phplrt/runtime` — без компилятора.

---

## 2. Установка

```bash
# В продакшене (только runtime: лексер + парсер + source)
composer require phplrt/runtime

# Для разработки (компилятор грамматик + CLI)
composer require phplrt/compiler --dev
```

Проверка CLI:

```bash
php vendor/bin/phplrt --version
```

---

## 3. Синтаксис PP3 (файл `.pp3`)

Формат `.pp3` — текущий формат грамматик (`.pp2` и `.pp` устарели).
Синтаксис близок к EBNF.

### 3.1. Комментарии

```pp3
// Однострочный комментарий

/*
   Многострочный комментарий
*/
```

### 3.2. Токены и пропуски

```pp3
%token T_DIGIT \d++
%token T_PLUS \+
%skip  T_WHITESPACE \s++
%skip  T_COMMENT //[^\n]*+
```

- `%token NAME REGEX` — объявить токен.
- `%skip NAME REGEX` — объявить токен, который **читается, но не попадает в парсер**
  (пробелы, комментарии).
- **Порядок важен:** лексер берёт **первое** совпадение, а не самое длинное.
  Поэтому длинные токены объявляются раньше коротких:

```pp3
%token T_POW  \*\*    // ✔ первым
%token T_STAR \*      // затем одинарная звёздочка
```

- **Одна декларация — одна строка.** Регулярка не может содержать литеральный
  пробел; используйте `\x20` или `\s`.
- Ключевые слова объявляются **до** `T_IDENT`, иначе `SELECT` распознается как
  идентификатор. Флаг `(?i)` делает匹配 регистронезависимым:

```pp3
%token T_SELECT /(?i)SELECT\b/
%token T_IDENT  /[A-Za-z_][A-Za-z0-9_]*/
```

### 3.3. Правила (rules)

```pp3
RuleName
    : <T_DIGIT> (::T_PLUS:: <T_DIGIT>)*
    ;
```

- `<T_NAME>` — прочитать токен и **сохранить** его в `$children`.
- `::T_NAME::` — прочитать токен и **выбросить** его (не попадает в `$children`).
- `Rule()` — вызвать другое правило.
- `|` — альтернатива («или»). Альтернативы пробуются по порядку, первая
  совпавшая побеждает.
- Квантификаторы:
  - `*` — ноль или более;
  - `+` — один или более;
  - `?` — ноль или один;
  - `{2,5}` — от двух до пяти.
- `%pragma root RuleName` — указать корневое правило, с которого начинается разбор.

**Пустая альтернатива:** в PP3 **нельзя** писать `Rule : ;` (пустое тело правила
вызывает ошибку `unexpected ";"`). Вместо этого используйте квантификатор `?` или `*`
на уровне родительского правила, либо отдельное правило с `*`:

```pp3
// ✔ Пустой список — через ?
List : Item()* ;

// ✔ Или через отдельное правило с *
List : Items()? ;
Items : Item() (::T_COMMA:: Item())* ;
```

> Директива `%empty` в PP3 **не поддерживается**.

### 3.4. Редукторы (reducers)

Редуктор — блок PHP-кода между `->` и телом правила. Выполняется, когда правило
совпало; возвращённое значение становится результатом правила для родителя.

```pp3
Number -> { return (int) $children->value; }
    : <T_DIGIT>
    ;
```

Доступные переменные внутри блока:

| Переменная | Что это |
|------------|---------|
| `$children` | Что совпало правило (массив или одно значение) |
| `$ctx` | Объект `Context` (полный контекст разбора) |
| `$offset` / `$begin` | Начало правила в байтах |
| `$length` | Длина правила в байтах |
| `$end` | Конец правила (`$offset + $length`) |
| `$source` | Источник, который разбирается |
| `$content` | Всё содержимое источника |
| `$rule` | ID правила, которое сейчас сворачивается |

**Важно про `$children`:**

- Если правило — **последовательность** (конкатенация или повторение), `$children`
  будет **массивом**:

```pp3
Pair : <T_DIGIT> <T_DIGIT> ;   // $children = [Token, Token]
List : <T_DIGIT>+ ;            // $children = [Token, Token, ...]
```

- В остальных случаях `$children` — **единственное значение**, которое правило
  распознало (результат вложенного правила или токен).

**Пример с построением AST:**

```pp3
Script -> {
    return new \Inilim\SqliteParser\Ast\Script($children);
}
    : Statement() (::T_SEMICOLON:: Statement())*
    ;
```

Если reducer не указан, правило возвращает «сырые» токены/значения вложенных
правил.

**Пустой блок `-> {}`** эквивалентен отсутствию редуктора.

---

## 4. Компиляция грамматики

### 4.1. Через CLI

```bash
# Проверить грамматику (ничего не пишет на диск)
php vendor/bin/phplrt check grammar/sqlite.pp3

# Проверить с подробным выводом (счётчики правил, токенов, редукторов)
php vendor/bin/phplrt check grammar/sqlite.pp3 -v

# Скомпилировать в PHP-класс
php vendor/bin/phplrt compile grammar/sqlite.pp3 src/Parser/SqliteParser.php \
    --class=SqliteParser \
    --namespace='Inilim\SqliteParser\Parser'
```

Полезные опции `compile`:

| Опция | Значение |
|-------|----------|
| `-c`, `--class` | Имя класса парсера |
| `-u`, `--use` | Импортировать класс (можно повторять) |
| `--php` | Целевая версия PHP (8.1+) |
| `--no-readonly` | Не помечать класс как `readonly` |

`check` завершается с кодом `0`, если грамматика валидна, и `1` — если нет.
Это удобно для CI: сломанная грамматика «валит» pull request.

### 4.2. Через PHP API

```php
use Phplrt\Compiler\Compiler;
use Phplrt\Source\FileSource;
use Phplrt\Source\StringSource;

// Проверка/разбор без записи на диск
$parser = (new Compiler())
    ->load(FileSource::createFromPathname(__DIR__ . '/grammar/sqlite.pp3'))
    ->getParser();

$result = $parser->parse(StringSource::createFromString('SELECT 1'));
```

Генерация кода:

```php
(new Compiler())
    ->load(FileSource::createFromPathname(__DIR__ . '/grammar/sqlite.pp3'))
    ->generate()
    ->withNamespaceName('Inilim\SqliteParser\Parser')
    ->withClassName('SqliteParser')
    ->withClassImport('Inilim\SqliteParser\Ast\Script')
    ->save(__DIR__ . '/src/Parser/SqliteParser.php');
```

---

## 5. Использование в runtime

Сгенерированный парсер — обычный класс, реализующий
`Phplrt\Contracts\Parser\ParserInterface`:

```php
use Inilim\SqliteParser\Parser\SqliteParser;
use Phplrt\Source\StringSource;

$parser = new SqliteParser();

$ast = $parser->parse(StringSource::createFromString(
    'SELECT id FROM users WHERE active = 1;'
));
```

Методы runtime-API:

- `$parser->parse($source)` — вернуть результат разбора или бросить исключение
  при синтаксической ошибке;
- `$parser->analyze($source)` — проанализировать без выбрасывания исключения
  (возвращает объект с ошибками).

Источник можно создать из строки или файла:

```php
use Phplrt\Source\StringSource;
use Phplrt\Source\FileSource;

$source = StringSource::createFromString('SELECT 1');
$source = FileSource::createFromPathname(__DIR__ . '/query.sql');
```

---

## 6. Разделение грамматики на файлы (`%include`)

Большие грамматики удобно разбивать на части:

```pp3
%include grammar/lexemes
%include grammar/expressions.pp3

%pragma root Script
```

- Путь в `%include` — **относительно файла, в котором написан `%include`**
  (а не относительно cwd). Это подтверждено документацией.
- Расширение можно опустить — компилятор попробует известные форматы.
- Один и тот же файл, включённый из нескольких мест, читается **один раз**.
- Декларации встают ровно на место `%include`, поэтому порядок токенов
  сохраняется.

> **Замечание для Windows:** если `%include` не находит файл, проверьте, что
> путь относительный и файл существует относительно **включающего** `.pp3`.
> Ошибка `GrammarNotFoundException` указывает и включаемый файл, и место
> включения.

---

## 7. Частые ошибки и как их избежать

### 7.1. `unexpected "%use"`

В PP3 **нет** директивы `%use`. Импорт классов делается одним из способов:

1. **FQCN прямо в редукторе** (самый простой и надёжный):

```pp3
Script -> { return new \App\Ast\Script($children); }
    : ...
    ;
```

2. Через опцию `--use` при компиляции:

```bash
php vendor/bin/phplrt compile grammar.pp3 Parser.php --class=Parser \
    --use='App\Ast\Script'
```

### 7.2. `unexpected ";"` после `:`

Причина: пустое тело правила (`Rule : ;`). В PP3 пустая альтернатива
выражается **не пустым телом**, а квантификатором `?` или `*` на родительском
правиле (см. §3.3). Директива `%empty` не поддерживается.

### 7.3. `unexpected "=>"`

Редукторы в PP3 пишутся через `-> { ... }`, а не через `=>`. Стрелка `->`
стоит **перед** телом правила:

```pp3
// ✔ правильно
Number -> { return (int) $children->value; }
    : <T_DIGIT>
    ;

// ✘ неправильно
Number : <T_DIGIT> => new Number(...) ;
```

### 7.4. `GrammarNotFoundException` при `%include`

Путь в `%include` отсчитывается от **файла, в котором написан `%include`**, а не
от cwd. Проверьте относительный путь и существование файла. При необходимости
можно передать абсолютный путь.

### 7.5. Токен не находится (попадает в `T_IDENT`)

Порядок объявления: ключевые слова — **до** `T_IDENT`. Многословные
конструкции (`ORDER BY`) — **до** однословных (`ORDER`, `BY`). Лексер берёт
первое совпадение, а не самое длинное.

### 7.6. `$children` не массив, хотя ожидался

Правило-«последовательность» даёт массив, правило-«одиночное значение» — скаляр
или объект. Чтобы гарантировать массив, оберните в правило с `*` или `+`:

```pp3
List : Item()* ;        // $children = [Item, ...]
```

Если `List` вызывается через `List()?` и не совпал, `$children` родителя будет
`null` или пустым массивом — учитывайте это в редукторе.

---

## 8. Пример для `inilim/sqlite-parser`

Минимальная рабочая грамматика (этап 2 плана) — в `grammar/sqlite.pp3`.
Ключевые моменты:

- Все токены SQLite 3.54 объявлены в одном файле (разделение через `%include`
  можно вернуть позже, когда инфраструктура стабилизируется).
- `Statement` пока заглушка на `::T_IDENT::`; на этапе 3 заменится реальными
  ветками `SelectStatement`, `InsertStatement`, `CreateTableStatement`, ...
- `Script` строится через `StatementList()?` — пустой ввод даёт `Script([])`.
- Редукторы используют FQCN (`\Inilim\SqliteParser\Ast\Script`) — без `%use`.

Проверка и сборка:

```bash
composer grammar:check
composer grammar:build
composer tests
```

---

## 9. Полезные ссылки

- Официальная документация: <https://phplrt.org/docs>
- Синтаксис PP3: <https://phplrt.org/docs/basics/grammar>
- Редукторы: <https://phplrt.org/docs/basics/reducers>
- Компиляция: <https://phplrt.org/docs/basics/compiler>
- CLI: <https://phplrt.org/docs/basics/cli>
- Quick Start: <https://phplrt.org/docs/guide/quick-start>
- GitHub: <https://github.com/phplrt/phplrt>