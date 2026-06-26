# Menu Obfuscation Plan

Дополнение к `theme-obfuscation.md`. Здесь — конкретный план для шести вариантов главного меню.

---

## 1. Инвентаризация классов

### Общие классы (fingerprint risk — одни и те же на всех сайтах)

| Класс / ID | Меню | Примечание |
|---|---|---|
| `#site-header` | boring, hierarchical, new-aside | ID в JS-селекторах |
| `.header-island` | boring, hierarchical, new-aside | |
| `.site-logo` | boring, hierarchical, new-aside, docs | |
| `.site-name` | boring, hierarchical, new-aside, docs | |
| `.menu-toggle` | boring, hierarchical, new-aside | |
| `.side-panel` | boring, new-aside | hierarchical использует `.side-panel` тоже, но стили разные |
| `.side-panel-header` | boring, new-aside | |
| `.menu-close` | boring, new-aside | |
| `.side-nav` | boring, new-aside, hierarchical | |
| `.side-menu-list` | boring, new-aside, hierarchical | **коллизия семантики — см. раздел 3** |
| `.menu-item-card` | boring, new-aside | **коллизия — разные стили** |
| `.menu-item-row` | boring, new-aside | |
| `.menu-thumb` | boring, new-aside, island | |
| `.menu-title` | boring, new-aside, island | |
| `.submenu-toggle` | boring, new-aside | |
| `.sub-menu-list` | boring, new-aside | **коллизия — drill vs accordion** |
| `.sub-sub-menu-list` | boring, new-aside | **коллизия** |
| `.sub-menu-item` | boring, new-aside | |
| `.sub-sub-menu-item` | boring, new-aside | |
| `.menu-overlay` | boring, new-aside, hierarchical | |
| `body.menu-open` | boring, new-aside, hierarchical | |
| `.is-active` | boring, new-aside, hierarchical, docs | |
| `.is-open` | boring, new-aside | |
| `.has-submenu` | boring, new-aside | |

### Уникальные пространства имён (уже хорошо изолированы)

| Префикс | Меню | Классов |
|---|---|---|
| `island-*` | island | `island-main`, `island-logo`, `island-name`, `island-trigger`, `island-dropdown`, `island-grid` |
| `docs-menu-*` | docs | весь набор |
| `drill-*` | boring | `drill-panel`, `drill-back`, `drill-title`, `drill-viewport`, `drill-corner`, `drill-panel--*`, `is-going-back` |
| `panel-*` | hierarchical (mobile) | `panel-header`, `panel-site-name`, `panel-close`, `panel-nav`, `panel-menu-list`, `panel-item`, `panel-item-row`, `panel-toggle`, `panel-sub-menu`, `panel-sub-sub-menu` |

### IDs как дополнительный fingerprint

| ID | Меню | Где используется |
|---|---|---|
| `#island-wrapper` | island | PHP + JS (getElementById нет, querySelector) |
| `#menu-toggle` | island | PHP + JS (`getElementById`) |
| `#island-dropdown` | island | PHP + aria-controls |
| `#site-header` | hierarchical | JS (`getElementById`) |
| `#hier-side-panel` | hierarchical | PHP + JS (`getElementById`) |
| `#hier-overlay` | hierarchical | PHP + JS (`getElementById`) |

---

## 2. Мёртвый код — удалить

### docs.scss — три класса определены, но в PHP не генерируются

```scss
.docs-menu-toggle   { display: none; ... }  // orphan
.docs-menu-close    { display: none; ... }  // orphan
.docs-menu-overlay  { display: none; ... }  // orphan — реальный оверлей это body.docs-menu-open + JS
```

Кнопка в PHP — `.docs-menu-toggle-btn`, а не `.docs-menu-toggle`. Классы `.docs-menu-toggle`, `.docs-menu-close`, `.docs-menu-overlay` нигде не выводятся.

### aside.scss — `.menu-close` фактически скрыта

```scss
.menu-close {
    visibility: hidden;
    opacity: 0;
    position: fixed;  // убрана из потока и из видимости
}
```

В `main-menu-new-aside.php` `.menu-close` присутствует в HTML и стилях, но JS её не показывает (`.closeMenu()` не добавляет видимость). В `main-menu-boring.php` кнопка закрытия тоже `.menu-close`, но boring.scss её скрывает через `display: none`. Обе можно убрать — в boring панель закрывается через оверлей и drill-back, в new-aside через оверлей.

---

## 3. Семантические коллизии — разделить

Эти классы одинаковы по имени, но стилизованы по-разному в каждом меню — это путает и создаёт хрупкую зависимость.

### `.side-menu-list`

| Меню | Семантика | Стиль |
|---|---|---|
| boring | корневая drill-панель (grid карточек) | `display: grid; aspect-ratio: 1/1` |
| hierarchical | горизонтальный navbar (flex) | `display: flex; height: 100%` |
| new-aside | вертикальный список карточек | `list-style: none` |

**Решение:** переименовать по-варианту:
- boring: `.drill-root-list` (уже в контексте `drill-*` namespace)
- hierarchical: `.nav-menu-list`
- new-aside: `.aside-menu-list`

### `.menu-item-card`

| Меню | Семантика |
|---|---|
| boring | квадратный drill-in tile с фото фоном |
| new-aside | карточка с превью 56×56 + текст, анимация slide-in |

**Решение:** boring → `.drill-card`, new-aside → `.aside-card`

### `.sub-menu-list` / `.sub-sub-menu-list`

| Меню | Семантика |
|---|---|
| boring | drill-панели 2-го и 3-го уровня (скрыты, выдвигаются) |
| new-aside | accordion-подменю (max-height: 0 → auto) |

**Решение:** boring → `.drill-sub-list` / `.drill-sub-sub-list` (логично в `drill-*` namespace), new-aside → `.aside-sub-list` / `.aside-sub-sub-list`

### `#menu-toggle` (island) vs `.menu-toggle` (boring, hierarchical, new-aside)

Island использует ID `#menu-toggle`, остальные — класс `.menu-toggle`. При конвертации в `ut-` разные правила замены для id vs class. Решение при добавлении prefix: island переходит на `.ut-island-toggle` (убираем ID, добавляем уточняющий namespace).

---

## 4. ID → class

Конвертировать перед добавлением `ut-` prefix:

| Было | Станет | Файлы |
|---|---|---|
| `id="island-wrapper"` | `class="ut-island-wrapper"` | island.php, island.scss |
| `id="menu-toggle"` | `class="ut-island-toggle"` | island.php, island.scss JS |
| `id="island-dropdown"` | `class="ut-island-dropdown"` | island.php, island.scss |
| `id="site-header"` (hier JS) | JS переключить на `.ut-site-header` | hier JS только |
| `id="hier-side-panel"` | → класс `.ut-side-panel` уже есть, убрать отдельный ID | hier PHP + JS |
| `id="hier-overlay"` | → класс `.ut-menu-overlay` уже есть, убрать отдельный ID | hier PHP + JS |

Hierarchical JS сейчас делает `getElementById('hier-side-panel')` — заменить на `querySelector('.ut-side-panel')`, `getElementById('hier-overlay')` → `querySelector('.ut-menu-overlay')`.

---

## 5. Система рандомной замены

Общий механизм описан в `theme-obfuscation.md`. Специфика для меню:

### sed покрывает JS внутри PHP

PHP-файлы меню содержат `<script>` блоки, где классы упоминаются как строки:

```js
document.querySelector('.menu-toggle')
document.querySelector('.side-panel')
panel.classList.add('is-active')
body.classList.add('menu-open')
```

Команда `sed -i 's/\but-/{{ css_prefix }}/g'` в provision.sh.j2 применяется к `*.php` файлам → все строки в `<script>` блоках тоже пройдут замену автоматически.

### ID-атрибуты требуют отдельного паттерна

После конвертации ID → class (раздел 4) проблема уходит. Если какие-то ID останутся с `ut-`:

```bash
sed -i 's/id="ut-/id="{{ css_prefix }}/g'
sed -i 's/getElementById("ut-/getElementById("{{ css_prefix }}/g'
```

### data-атрибуты не трогаем

`data-drill-id`, `data-drill-target`, `data-drill-title` — это внутренние state-атрибуты JS, не fingerprint. Менять не нужно.

### aria-* не трогаем

`aria-label`, `aria-expanded`, `aria-controls` — a11y, не CSS fingerprint.

---

## 6. Порядок работ

### Фаза 1 — Cleanup (быстро, без риска)

- [ ] Удалить из `docs.scss` orphaned правила: `.docs-menu-toggle`, `.docs-menu-close`, `.docs-menu-overlay`
- [ ] Удалить `.menu-close` из `boring.scss` (display:none) и `aside.scss` (hidden), убрать из обоих PHP шаблонов

### Фаза 2 — Collision resolution (переименование классов)

Для каждой пары: правка PHP (HTML-разметка), SCSS (селекторы), JS (querySelector строки) — в одном коммите.

- [ ] `.side-menu-list` → `.drill-root-list` (boring) / `.nav-menu-list` (hierarchical) / `.aside-menu-list` (new-aside)
- [ ] `.menu-item-card` → `.drill-card` (boring) / `.aside-card` (new-aside)
- [ ] `.sub-menu-list` / `.sub-sub-menu-list` → `drill-sub-*` (boring) / `aside-sub-*` (new-aside)
- [ ] Walker в `main-menu-boring.php` и `main-menu-new-aside.php` использует `Aside_Walker` — проверить, что Walker генерирует нужные классы

### Фаза 3 — ID → class

- [ ] island: `#island-wrapper`, `#menu-toggle`, `#island-dropdown` → классы
- [ ] hierarchical: убрать `#hier-side-panel`, `#hier-overlay`, JS переключить на querySelector по классу

### Фаза 4 — ut- prefix sweep (общий для всей темы)

Эта фаза входит в общий план из `theme-obfuscation.md`. Меню-специфика:
- После фаз 1–3 пространства имён чистые, коллизий нет
- Все кастомные классы получают `ut-`: `.ut-header-island`, `.ut-side-panel`, `.ut-drill-card`, `.ut-island-wrapper`, и т.д.
- Shared state-классы тоже: `body.ut-menu-open`, `.ut-is-active`, `.ut-is-open`

### Фаза 5 — provision.sh.j2

Уже описан в `theme-obfuscation.md`. Дополнений для меню нет — `*.php` и `*.css` уже покрыты.

---

## 7. Что снизит fingerprint больше всего

По убыванию эффекта:

1. **ut- prefix + sed** — главный эффект, уникальные классы на каждом домене
2. **ID → class** — ID `#island-wrapper` сейчас виден в исходнике как статичная строка
3. **Collision resolution** — снижает «паттерн похожести» при ручном осмотре разных сайтов
4. **Cleanup** — мёртвый CSS убирает лишние строки из скомпилированного файла
