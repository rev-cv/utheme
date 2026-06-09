# Theme Fingerprint Obfuscation

Цель: скрыть идентификацию темы от инструментов типа Wappalyzer, WhatCMS, BuiltWith и ручного просмотра исходника.

---

## Уже реализовано

### Рандомизация идентичности темы (`core/theme_identity.py`)

- 31 × 4 = 124 уникальных записи `(название, slug, автор-студия)`
- Выбор: день месяца + `md5(домен) % 4` → детерминировано (один сайт всегда получает одно имя)
- Данные прокидываются через `core/manifest.py → site_config()` в manifest

### Патчинг темы при деплое (`templates/provision.sh.j2`, секция THEME)

- Папка `utheme` копируется под рандомным именем: `/themes/clearwater/`
- В скопированной `style.css` через `sed` заменяются:
  - `Theme Name: UTheme` → `Theme Name: Clearwater`
  - `Author: R3` → `Author: WebCraft Studio`
- Оригинальная папка `utheme` не удаляется (bind-mount с хоста)

**Что это закрывает:** URL ассетов (`/themes/clearwater/style.css`), заголовки style.css — именно по ним работают все инструменты fingerprinting.

---

## Не реализовано — CSS классы в HTML

### Проблема

Классы в HTML (`.article-card`, `.island-main`, `.footer-column`) одинаковы на всех сайтах. Слабый, но существующий fingerprint.

### Выбранный подход — префикс `ut-` в исходниках + замена при деплое

**Шаг 1 — рефактор исходников (одноразовый)**

Добавить `ut-` перед всеми кастомными классами:

- `utheme/src/*.scss` — все селекторы: `.ut-island-main`, `.ut-article-card`, …
- `utheme/components/*.php`, `utheme/inc/*.php`, `utheme/*.php` — атрибуты `class="ut-…"`
- `core/wp_html.py` — классы, которые pipeline вставляет в `.wp` файлы

Граница чёткая: `ut-*` — мои классы, всё остальное (`wp-block-*`, `alignwide`, …) — WP/плагины, не трогать.

**Шаг 2 — генерация css-префикса при деплое**

В `core/theme_identity.py` (уже есть `rng` сидированный доменом):

```python
css_prefix = ''.join(rng.choices('abcdefghjkmnpqrstvwxyz', k=3)) + '-'
# → "kfn-", "bxq-", "mtr-", ...
```

Добавить в возвращаемый dict как `"css_prefix"`, прокинуть через `manifest.py → site_config()` в шаблон.

**Шаг 3 — замена в скопированной теме (`templates/provision.sh.j2`)**

После `cp -r utheme /themes/{{ slug }}/` — один `find + sed`:

```bash
find /themes/{{ slug }}/ -type f \( -name "*.css" -o -name "*.php" \) \
  -exec sed -i 's/\but-/{{ css_prefix }}/g' {} +
```

Заменяет `ut-` → `kfn-` во всех CSS и PHP файлах скопированной темы. Оригинал (bind-mount) не трогается.

**Итог:** нулевой runtime overhead, каждый сайт имеет уникальные CSS-классы в HTML.

---

## Лёгкие wins — ✅ реализовано

Две строки в `utheme/functions.php`:

```php
// Убрать WordPress version из <head>
remove_action('wp_head', 'wp_generator');

// Убрать ?ver=6.7.x из URL всех ассетов
add_filter('style_loader_src',  fn($src) => remove_query_arg('ver', $src));
add_filter('script_loader_src', fn($src) => remove_query_arg('ver', $src));
```

Убирает ещё один явный fingerprint — версию WP в URL ассетов.

---

## Реализовано — YAML-маппинг классов (Phase 1)

`core/theme_obfuscate.py` переписан:
- `_load_variants()` — загружает все `core/keyclass/keyclass-*.yml` при импорте
- Валидация: нет дублей ключей, нет дублей имён в каждом столбце
- `make_class_map(site_url)` — `sha256(domain) % N_THEMES` выбирает столбец
- `apply_class_map_to_file` — longest-first string replace по всем `.php/.css/.scss`

`core/keyclass/keyclass-main-menu.yml` — 21 ключ × 4 темы:
- Только блоки (BEM) + standalone state-классы
- Элементы (`ut-panel__head` и т.п.) не нужны — замена `ut-panel` → `amber` покрывает `ut-panel__head` → `amber__head` автоматически

### BEM-рефактор меню (Phase 1, завершён)

Все 5 PHP-компонентов и 5 SCSS-файлов главного меню переведены на BEM:
- `ut-item-card` → `ut-item` (блок), `ut-item__row`, `ut-item__thumb`, `ut-item__label`, `ut-item--has-sub`
- `ut-panel-head` → `ut-panel__head`, `ut-panel__body`, `ut-panel__list`, `ut-panel__item`, …
- `ut-drill-root-list` → `ut-drill__root`, `ut-drill__viewport`, `ut-drill__panel`, …
- `ut-island-wrapper` → `ut-island` (блок), `ut-island__bar`, `ut-island__dropdown`, …
- `ut-header-island` → `ut-site-header__island` (boring/hier) / `ut-island__bar` (island)
- `ut-nav-menu-list` → `ut-nav__list`, `ut-menu-item` → `ut-nav__item`

Ключей в YAML: 81 → 60 → 57 (newspaper) → **21 (BEM)**.

### Phase 2 — обфускация `__elem` (отложено)

Текущая схема заменяет только имя блока: `ut-panel` → `amber`, и это автоматически даёт `ut-panel__head` → `amber__head`.

**`__head`, `__body`, `__list`, `__item` и т.п. остаются видимыми.**

Запланировано на Phase 2 — глобальный проход с заменой BEM-элементов на короткие суффиксы (например, `__head` → `__a`, `__body` → `__b`). Это дополнительный уровень, не блокирует Phase 1.

---

## Следующие шаги

1. Добавить `css_prefix` в `get_theme_identity()` → `manifest.py` → `site_config()`
2. Добавить `sed`-замену в `templates/provision.sh.j2`
3. Обфускация content-классов в `core/wp_html.py` (`info-callout`, `card-grid`, `at-a-glance`, …)
4. [Phase 2] Обфускация `__elem` суффиксов
