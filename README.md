# WordPress Auto-Deploy Pipeline

Автоматизированный конвейер для развёртывания WordPress-сайтов из подготовленных HTML-материалов.  
Принимает на вход папку `spec/` с контентом, преобразует его в Gutenberg-блоки и разворачивает готовый сайт в Docker.

---

## Что делает

1. Определяет тип структуры проекта в `spec/`
2. Проверяет целостность файлов
3. Сжимает изображения → WebP
4. Конвертирует HTML-страницы → Gutenberg-блоки (`.wp`)
5. Проверяет внешние ссылки
6. Собирает `manifest.json` — полное описание сайта
7. Генерирует `wp-conf/provision.sh` — bash-скрипт установки WordPress
8. Запускает Docker-контейнер с WordPress
9. Внутри контейнера: устанавливает WP, импортирует медиа, создаёт страницы, меню, настройки

---

## Структуры контента (`spec/`)

Пайплайн автоматически определяет тип структуры из трёх поддерживаемых:

### Структура 1 — Flat5
```
spec/
  PILLAR/          ← главная страница
  CL1/ … CL5/     ← основные кластерные статьи
  ADD PAGES/       ← технические страницы
  CLUSTERS ADD/    ← опционально, отложенные публикации
```

### Структура 2 — Clusters Main
```
spec/
  PILLAR/
  CLUSTERS MAIN/
    CL1/ … CL5/
  ADD PAGES/
  CLUSTERS ADD/    ← опционально
```

### Структура 3 — Hub/Pillar
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

---

## Быстрый старт

### 1. Настройка окружения

Скопируйте и заполните `.env`:

```env
HOST_PORT=8083
SITE_URL=http://localhost:8083
CONTAINER_NAME=my-site-com
SITE_TITLE="My Site"
SITE_LANG=EN
ADMIN_EMAIL=admin@my-site.com

DB_NAME=my_site_com
DB_USER=my_site_com
DB_PASSWORD=<генерируется автоматически>

SCHEDULE_PATTERN="3d 2-3p (10-21)"
```

**`SCHEDULE_PATTERN`** — расписание отложенных публикаций:
- `3d 2-3p (10-21)` — каждые 3 дня по 2–3 поста в промежутке 10:00–21:00
- `0d 1p (8-21)` — каждый день 1 пост с 8 до 21

**`SITE_LANG`** — код языка: `EN`, `RU`, `DE`, `FR`, `ES`, `IT`, `PL`, `PT`, `NL`, `CZ`, `SK`, `ET`, `LV`, `RO`, `SV`, `LT`, `BG`, `SL`, `HU`, `FI`, `DA`, `GR`

### 2. Подготовка контента

Поместите контент в папку `spec/` согласно одной из структур выше.

Требования к файлам:
- Статьи — `.html` с тегами `<h1>`, `<meta name="description">`, `<h2>`/`<p>`/`<img>`
- Изображения — `.webp`, `.jpg`, `.png` (автоматически сжимаются до 120 KB)
- Логотип — `logo.webp` (или `.png`, `.jpg`) в корне `spec/` или `HUB/PILLAR/` или `PILLAR/`
- Фавикон — `favicon.png` (или `icon.*`) там же

### 3. Запуск

```bash
uv run setup.py
```

После завершения в консоль выводятся учётные данные WordPress и application password.  
Они также сохраняются в `*_access.txt` (удалите после копирования).

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

## Удаление проекта

Поскольку в текущей реализации контейнер с бд и контейнер wp разнесены, удаление wp требует того, чтобы так же была удалена таблица удаляемого сайта.

команда удаления для Windows (заменить `<DB_PASSWORD>` на пароль, который можно посмотреть в .env лежащим в папке с контейнером базы данной):
```sh
docker compose down -v; docker exec wp_shared_db mariadb -uroot -p<DB_PASSWORD> -e $('DROP DATABASE IF EXISTS ' + [char]96 + 'footchmondial2026-com' + [char]96 + ';')
```

Для нормальных систем:


### Лимиты ресурсов

```env
WP_CPU_LIMIT=0.5    # доля CPU
WP_MEM_LIMIT=256m   # RAM
```

---

## Структура проекта

```
pipeline.py            ← точка входа
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
  detect_structure.py  ← диспетчер определения структуры
  structures/
    struc1.py          ← Flat5
    struc2.py          ← Clusters Main
    struc3.py          ← Hub/Pillar
  check_structure.py   ← валидация и нормализация файлов
  convertation_to_wp.py   ← HTML → Gutenberg-блоки
  convertation_images.py  ← сжатие изображений
  generate_sh.py          ← рендеринг provision.sh из Jinja2
  docker_setup.py         ← управление Docker и деплой
  enrich_with_schedule.py ← планировщик публикаций
  extract_meta_from_html.py
  link_images_to_articles.py
  translations.py
```

---

## Технические детали

### HTML → Gutenberg

Конвертер обрабатывает:
- `<h2>`–`<h6>` → `<!-- wp:heading -->`
- `<p>` → `<!-- wp:paragraph -->`
- `<img>` → `<!-- wp:image {"id":N,"sizeSlug":"full"} -->`
- `<figure>` → `<!-- wp:image -->` с опциональным `<figcaption>`
- `<ul>` / `<ol>` → `<!-- wp:list -->`
- `<table>` → `<!-- wp:table -->`

Изображения импортируются через `media_handle_sideload()` одним PHP-процессом, затем ID и URL вставляются в `.wp`-файлы вместо плейсхолдеров `%%IMGID:file.webp%%` и `%%IMGSRC:file.webp%%`.

### Черновики (`_dynamic.txt`)

Если папка статьи содержит только `_dynamic.txt` (нет `.html`), создаётся страница-черновик (`post_status=draft`) с нужным slug и родителем. Контент добавляется позже вручную.

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
```

Установка (через `uv` или `pip`):
```bash
uv run pipeline.py   # автоустановка зависимостей
# или
pip install python-dotenv requests Pillow bs4 transliterate jinja2
python pipeline.py
```
