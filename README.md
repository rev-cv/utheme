# WordPress Auto-Deploy Pipeline

Автоматизированный конвейер для развёртывания WordPress-сайтов из подготовленных HTML/MD-материалов.  
Принимает на вход папку `spec/` с контентом, преобразует его в Gutenberg-блоки и разворачивает готовый сайт в Docker.

---

## Что делает

1. Нормализует имена файлов и папок
2. Определяет тип структуры проекта в `spec/`
3. Проверяет целостность файлов
4. Сжимает изображения → WebP
5. Конвертирует HTML/MD-страницы → Gutenberg-блоки (`.wp`)
6. Проверяет внешние ссылки
7. Собирает `manifest.json` — полное описание сайта
8. Проверяет внутренние ссылки
9. Генерирует `wp-conf/provision.sh` — bash-скрипт установки WordPress
10. Запускает Docker-контейнер с WordPress; внутри: устанавливает WP, импортирует медиа, создаёт страницы, меню, настройки
11. Устанавливает и активирует плагины
12. Запускает SCSS-watcher (Windows)

---

## Структуры контента (`spec/`)

Пайплайн автоматически определяет тип структуры из четырёх поддерживаемых:

### Структура 1 — CL5_2025 (Flat5)
```
spec/
  PILLAR/          ← главная страница
  CL1/ … CL5/     ← основные кластерные статьи
  ADD PAGES/       ← технические страницы
  CLUSTERS ADD/    ← опционально, отложенные публикации (CL1–CL30)
```

### Структура 2 — CL5_2026 (Clusters Main)
```
spec/
  PILLAR/
  CLUSTERS MAIN/
    CL1/ … CL5/
  ADD PAGES/
  CLUSTERS ADD/    ← опционально
```

### Структура 3 — FWC_2026 (Hub/Pillar)
```
spec/
  HUB/
    PILLAR/
      index.html   ← главная страница
      logo.webp
      favicon.png
  TITLE - slug/    ← секционные страницы (в главном меню)
    slug.html      ← контент секции
    child-slug/    ← дочерние статьи (parent = slug)
      child-slug.html
      _dynamic.txt ← без HTML → создаётся черновик
  ADD PAGES/
    page.html              ← утилитарные страницы (в footer)
    TITLE - slug/          ← утилитарные с меню (в конец main menu)
```

> В структуре 3 порядок секций в меню определяется алфавитным порядком папок.  
> Название пункта меню берётся из `TITLE` (часть до ` - ` в имени папки), а не из заголовка статьи.

### Структура 4 — FSR_2026 (FileSystem Routing)

Файловая система используется как источник маршрутов — по аналогии с Next.js App Router. Слаги и иерархия берутся напрямую из имён папок.

**Обнаружение:** в корне `spec/` есть `index.html` или `index.md`, но нет папок `PILLAR/` и `HUB/`.

```
spec/
  index.html              ← домашняя страница
  casino-sites [1M2;Casinos][U]/
    casino-sites.html
    best-casinos/
      best-casinos.html
  about [F;About Us]/
    about.html
```

**Флаги в именах папок:**

| Флаг | Описание |
|------|----------|
| `[M]` | Добавить в главное меню |
| `[<order>M<depth>;<label>]` | Позиция, глубина подменю, подпись (`[1M2;Best Sites]`) |
| `[F]` | Добавить в меню футера |
| `[<order>F;<label>]` | Позиция и подпись в футере |
| `[U]` | Пометить категорией "Utility Pages" |
| `[DLY]` | Отложенная публикация (случайные дата/время) |
| `[DLY=YYYY-MM-DD]` | Фиксированная дата, случайное время |
| `[DLY=YYYY-MM-DDThh.mm.ss]` | Фиксированные дата и время (`:` заменяется на `.`) |

Папка без `index.html` / `index.md` считается **маршрутным контейнером**: её дочерние страницы продвигаются на уровень выше, наследуя флаги контейнера.

---

## Быстрый старт

### 1. Настройка окружения

Скопируйте и заполните `.env`:

```env
HOST_PORT=8081
SITE_URL=http://localhost:${HOST_PORT}

THEME_SLUG="utheme"
CONTAINER_NAME="my-site-com"
SITE_TITLE="My Site"
SITE_LANG=EN

ADMIN_USER="admin"
ADMIN_EMAIL="admin@my-site.com"
WP_APP_PASSWORD="<ваш app password>"

DB_NAME="my-site-com"
DB_USER="my-site-com"
DB_PASSWORD=""        # генерируется автоматически если пусто

WP_CPU_LIMIT=0.5
WP_MEM_LIMIT=256m

SCHEDULE_PATTERN="3d 2-3p (10-21)"

# Плагины из официального каталога WP (slug:yes — установить+активировать, slug:no — только установить)
WP_PLUGINS="wpvivid-backuprestore:no"
```

**`SCHEDULE_PATTERN`** — расписание отложенных публикаций:
- `3d 2-3p (10-21)` — каждые 3 дня по 2–3 поста в промежутке 10:00–21:00
- `0d 1p (8-21)` — каждый день 1 пост с 8 до 21

**`SITE_LANG`** поддерживает три формата:
- Короткий код: `EN`, `FR`, `DE`, `PL`, `CZ`, `PT`, `IT`, `NL`, `ES`, `SK`, `ET`, `LV`, `RO`, `SV`, `LT`, `BG`, `SL`, `HU`, `FI`, `DA`, `RU`, `GR`, `HR`, `NO`, `LB`, `GA`, `TR`
- Псевдоним страны: `EE` → `et_EE`, `SE` → `sv_SE`, `AT` → `de_AT`
- Полный WP locale: `fr_BE`, `fr_CA`, `de_CH`, `pt_BR`, `en_GB`, `nl_BE` и др.

### 2. Подготовка контента

Поместите контент в папку `spec/` согласно одной из структур выше.

Требования к файлам:
- Статьи — `.html` или `.md` с тегами `<h1>`/`#`, `<meta name="description">`, `<h2>`/`<p>`/`<img>`
- Изображения — `.webp`, `.jpg`, `.png` (автоматически сжимаются до 120 KB)
- Логотип — `logo.webp` (или `.png`, `.jpg`) в корне `spec/` или `HUB/PILLAR/` или `PILLAR/`
- Фавикон — `favicon.png` (или `icon.*`) там же

### 3. Запуск

```bash
uv run pipeline.py
```

После завершения в консоль выводятся учётные данные WordPress и application password.

---

## Dev-режим (SCSS watch)

В проекте есть сервис `sass`, который следит за изменениями в `utheme/src/style.scss` и компилирует CSS на лету.

### Автозапуск вместе с сайтом

```bash
docker compose --profile dev up -d
```

### Запустить вручную (только sass)

```bash
docker compose up -d sass
```

### Остановить sass

```bash
docker compose stop sass
```

Исходники SCSS находятся в `utheme/src/`, скомпилированный файл — `utheme/style.css`.

---

## Конфигурация Docker

Сайт запускается в изолированном контейнере, подключённом к общей MariaDB.

```yaml
# docker-compose.yml (упрощённо)
wordpress:
  container_name: "${CONTAINER_NAME}"
  ports:
    - "127.0.0.1:${HOST_PORT}:80"
  volumes:
    - wp_html:/var/www/html       # именованный volume
    - ./utheme → themes/utheme
    - ./uploads → wp-content/uploads
    - ./plugins → wp-content/plugins
```

Внешние сети (должны существовать до запуска):
- `web_network` — для Nginx Proxy Manager / reverse proxy
- `shared_db_network` — для общей MariaDB

### Лимиты ресурсов

```env
WP_CPU_LIMIT=0.5    # доля CPU
WP_MEM_LIMIT=256m   # RAM
```

---

## Удаление проекта

Поскольку контейнер с БД и контейнер WP разнесены, при удалении нужно также удалить базу данных сайта.

```bash
uv run destroy.py
```

Или вручную (Windows, заменить `<DB_PASSWORD>` и `<DB_NAME>`):
```powershell
docker compose down -v; docker exec wp_shared_db mariadb -uroot -p<DB_PASSWORD> -e $('DROP DATABASE IF EXISTS ' + [char]96 + '<DB_NAME>' + [char]96 + ';')
```

---

## Структура проекта

```
pipeline.py            ← точка входа (12 фаз)
destroy.py             ← удаление контейнера и БД
.env                   ← конфигурация (не коммитить!)
spec/                  ← исходный контент
staging/               ← временные файлы (images/, pages/)
manifest.json          ← сгенерированный манифест сайта
wp-conf/
  provision.sh         ← генерируется, выполняется и удаляется
  .htaccess
  wp-config.php
  uploads.ini
utheme/                ← тема WordPress
plugins/               ← плагины WordPress
uploads/               ← медиафайлы сайта
templates/
  provision.sh.j2      ← Jinja2-шаблон скрипта установки
core/
  structure_detect.py  ← диспетчер определения структуры
  structures/
    s1_cl5_2025.py     ← CL5_2025 (Flat5)
    s2_cl5_2026.py     ← CL5_2026 (Clusters Main)
    s3_fwc_2026.py     ← FWC_2026 (Hub/Pillar)
    s4_fsr_2026.py     ← FSR_2026 (FileSystem Routing)
  normalize.py            ← нормализация имён файлов
  structure_validate.py   ← проверка целостности
  find_images.py          ← поиск изображений
  compress_images.py      ← сжатие изображений → WebP
  branding.py             ← обработка логотипа и фавикона
  wp_html.py              ← HTML → Gutenberg-блоки
  wp_md.py                ← Markdown → Gutenberg-блоки
  links.py                ← проверка внешних и внутренних ссылок
  extract_meta.py         ← извлечение мета-данных
  manifest.py             ← сборка manifest.json
  generate_sh.py          ← рендеринг provision.sh из Jinja2
  docker_setup.py         ← управление Docker и деплой
  enrich_with_schedule.py ← планировщик публикаций
  translations.py         ← карта языковых кодов
```

---

## Технические детали

### HTML/MD → Gutenberg

Конвертеры (`wp_html.py`, `wp_md.py`) обрабатывают:
- `<h2>`–`<h6>` / `##`–`######` → `<!-- wp:heading -->`
- `<p>` / параграф MD → `<!-- wp:paragraph -->`
- `<img>` → `<!-- wp:image {"id":N,"sizeSlug":"full"} -->`
- `<figure>` → `<!-- wp:image -->` с опциональным `<figcaption>`
- `<ul>` / `<ol>` → `<!-- wp:list -->`
- `<table>` → `<!-- wp:table -->`
- `<details>` / FAQ-шорткоды `[faq]…[/faq]` → accordion-блоки

Изображения импортируются через `media_handle_sideload()` одним PHP-процессом, затем ID и URL вставляются в `.wp`-файлы вместо плейсхолдеров `%%IMGID:file.webp%%` и `%%IMGSRC:file.webp%%`.

### Черновики (`_dynamic.txt`)

Если папка статьи содержит только `_dynamic.txt` (нет `.html`/`.md`), создаётся страница-черновик (`post_status=draft`) с нужным slug и родителем. Контент добавляется позже вручную.

### SEO-поля

Тема использует кастомный плагин с полями:
- `_custom_seo_title` → SEO Title (мета-тег `<title>`)
- `_custom_seo_headline` → Social / Headline (OpenGraph, Schema.org)
- `_custom_seo_desc` → Meta Description

Значения заполняются из манифеста и поддерживают плейсхолдеры `$$SITENAME$$` и `$$CURRENT_DATE$$`.

---

## Зависимости

```toml
python >= 3.13
python-dotenv
requests
Pillow
bs4
transliterate
jinja2
markdown-it-py
```

Установка (через `uv` или `pip`):
```bash
uv run pipeline.py   # автоустановка зависимостей
# или
pip install python-dotenv requests Pillow bs4 transliterate jinja2 markdown-it-py
python pipeline.py
```
