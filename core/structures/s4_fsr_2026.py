"""
Structure 4 — FSR_2026 — FileSystem Routing 2026
=================================================

ОПИСАНИЕ
--------
Структура использует файловую систему как источник маршрутов (по аналогии с
Next.js App Router). Слаги страниц и иерархия берутся напрямую из имён папок.

ОБНАРУЖЕНИЕ (detect)
--------------------
Активируется при одновременном соблюдении трёх условий:
  • В корне spec/ есть index.html или index.md   — домашняя страница
  • В корне нет папки PILLAR/                    — исключает CL5_2025 / CL5_2026
  • В корне нет папки HUB/                       — исключает FWC_2026

ФОРМАТ ИМЕНИ ПАПКИ
------------------
  <slug> [ФЛАГ1][ФЛАГ2]...

Из имени папки вырезаются все блоки в квадратных скобках, остаток обрезается
по краям — это slug. Допустимые символы slug: a–z, 0–9, дефис, нижнее
подчёркивание, пробел. Недопустимые символы удаляются с уведомлением в лог.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ФЛАГИ
-----

  [M]   — добавить страницу в главное меню, без подменю.

  Полная форма: [<order>M<depth>;<label>]
    <order>  — целое число, порядковый номер в меню (опционально, перед M)
    <depth>  — целое число, глубина раскрытия подменю (опционально, после M)
    <label>  — текст ссылки в меню вместо slug (опционально, после `;`)

    Примеры:
      [M]              → в меню, label=slug, без подменю, порядок авто
      [M2]             → в меню, раскрыть 2 уровня подменю
      [1M]             → в меню на позиции 1
      [1M3]            → позиция 1, 3 уровня подменю
      [M;Best Casinos] → без номера, без подменю, label="Best Casinos"
      [1M3;Casinos UK] → позиция 1, 3 уровня, label="Casinos UK"

  [F]   — добавить страницу в меню футера.

  Полная форма: [<order>F;<label>]
    <order>  — целое число, порядковый номер в меню (опционально, перед F)
    <label>  — текст ссылки вместо slug (опционально, после `;`)

    Примеры:
      [F]            → в футере, label=slug, порядок авто
      [1F]           → в футере на позиции 1
      [F;Footer]     → в футере, label="Footer"
      [1F;Footer]    → позиция 1, label="Footer"

  [U]   — пометить страницу категорией "Utility Pages".

  [DLY]                    — отложенная публикация, случайные дата и время.
  [DLY=YYYY-MM-DD]         — фиксированная дата, случайное время (7–21 ч).
  [DLY=YYYY-MM-DDThh.mm.ss]— фиксированные дата и время.
                             Знак `:` заменяется на `.` (ограничение Windows).
                             При ошибке разбора — откат к случайной дате + лог.

  Флаги комбинируются в любом порядке:
    casino-sites [1M2;Casinos][U][DLY=2026-06-01]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ОБРАБОТКА ПАПОК БЕЗ index-ФАЙЛА (контейнеры)
---------------------------------------------
Если в папке нет index.html / index.md, она считается маршрутным контейнером:

  • Её дочерние папки продвигаются на уровень выше (контейнер пропускается).
  • Флаги контейнера наследуются дочерними папками и мержатся с их собственными:
      — булевы флаги (M, F, U): OR
      — числовые (order, depth): приоритет у дочерней, если задана
      — label: приоритет у дочерней, если задана
      — publish_at: приоритет у дочерней, если задана
  • Процесс рекурсивен: цепочка пустых контейнеров схлопывается полностью.
  • Если внутри контейнера нигде не нашлось index-файла — папка игнорируется.

  Пример:
    check [U]/               — нет index, контейнер с флагом [U]
      licence-register-checks [M]/  — есть index.html
    →  страница "licence-register-checks", флаги [U][M], parent=None

ДОМАШНЯЯ СТРАНИЦА
-----------------
  index.html или index.md в корне spec/ — всегда домашняя страница
  (slug="index", parent=None), вне меню, флаги не применяются.
"""

import re
import random
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path

from ._shared import _find_content_file, _node, _page


# ── Регулярные выражения ──────────────────────────────────────────────────────

# Флаг M: [<order>M<depth>;<label>] — все части опциональны
_RE_M = re.compile(r'\[(\d*)M(\d*)(?:;([^\]]*))?\]')
_RE_F = re.compile(r'\[(\d*)F(?:;([^\]]*))?\]')
_RE_U = re.compile(r'\[U\]')
_RE_W = re.compile(r'\[W\]')
_RE_A = re.compile(r'\[A\]')
_RE_N = re.compile(r'\[N\]')
# Флаг DLY: [DLY] или [DLY=значение]
_RE_DLY = re.compile(r'\[DLY(?:=([^\]]+))?\]')
# Все флаги вместе — для вырезания из имени папки
_RE_ANY_FLAG = re.compile(
    r'\[\d*M\d*(?:;[^\]]*)?\]'   # M
    r'|\[\d*F(?:;[^\]]*)?\]'       # F
    r'|\[U\]'                      # U
    r'|\[W\]'                      # W
    r'|\[A\]'                      # A
    r'|\[N\]'                      # N
    r'|\[DLY(?:=[^\]]*)?\]'        # DLY
)
# Недопустимые символы в slug
_RE_INVALID = re.compile(r'[^a-z0-9\-_ ]')


# ── Флаги ─────────────────────────────────────────────────────────────────────

@dataclass
class _Flags:
    in_main:    bool       = False
    m_order:    int | None = None
    m_depth:    int        = 0
    m_label:    str | None = None
    in_footer:  bool       = False
    f_order:    int | None = None
    f_label:    str | None = None
    utility:    bool       = False
    about_us:   bool       = False
    list_page:  bool       = False
    news_page:  bool       = False
    publish_at: str        = "now"


def _parse_flags(name: str) -> _Flags:
    f = _Flags()

    m = _RE_M.search(name)
    if m:
        f.in_main = True
        f.m_order = int(m.group(1)) if m.group(1) else None
        f.m_depth = int(m.group(2)) if m.group(2) else 0
        label     = (m.group(3) or "").strip()
        f.m_label = label or None

    fm = _RE_F.search(name)
    if fm:
        f.in_footer = True
        f.f_order   = int(fm.group(1)) if fm.group(1) else None
        label       = (fm.group(2) or "").strip()
        f.f_label   = label or None

    if _RE_U.search(name):
        f.utility = True

    if _RE_W.search(name):
        f.about_us = True

    if _RE_A.search(name):
        f.list_page = True

    if _RE_N.search(name):
        f.news_page = True

    dly = _RE_DLY.search(name)
    if dly:
        f.publish_at = _resolve_publish_at(dly.group(1))

    return f


def _merge_flags(parent: _Flags, child: _Flags) -> _Flags:
    """Складывает флаги контейнера-родителя с флагами дочерней папки."""
    return _Flags(
        in_main    = parent.in_main   or child.in_main,
        m_order    = child.m_order    if child.m_order is not None else parent.m_order,
        m_depth    = max(parent.m_depth, child.m_depth),
        m_label    = child.m_label    or parent.m_label,
        in_footer  = parent.in_footer or child.in_footer,
        f_order    = child.f_order    if child.f_order is not None else parent.f_order,
        f_label    = child.f_label    or parent.f_label,
        utility    = parent.utility    or child.utility,
        about_us   = parent.about_us   or child.about_us,
        list_page  = parent.list_page  or child.list_page,
        news_page  = parent.news_page  or child.news_page,
        publish_at = child.publish_at if child.publish_at != "now" else parent.publish_at,
    )


# ── Slug ──────────────────────────────────────────────────────────────────────

def _extract_slug(name: str) -> str:
    return _RE_ANY_FLAG.sub("", name).strip()


def _validate_slug(raw: str, folder: Path, errors: list[str]) -> str:
    cleaned = _RE_INVALID.sub("", raw).strip("-_ ")
    if cleaned != raw:
        bad = sorted({c for c in raw if _RE_INVALID.match(c)})
        errors.append(
            f"Недопустимые символы в slug '{raw}' ({folder.name}): {bad}\n"
            f"        Возможно, флаг записан с ошибкой и не был распознан."
        )
    if not cleaned:
        errors.append(f"Пустой slug в папке '{folder.name}' — проверьте имя.")
    return cleaned


# ── Публичный API ─────────────────────────────────────────────────────────────

def detect(spec_dir: Path) -> bool:
    has_index = (spec_dir / "index.html").exists() or (spec_dir / "index.md").exists()
    no_pillar = not (spec_dir / "PILLAR").is_dir()
    no_hub    = not (spec_dir / "HUB").is_dir()
    return has_index and no_pillar and no_hub


def build(spec_dir: Path) -> dict:
    pages:             list[dict]  = []
    menu_register:     list[tuple] = []  # (slug, _Flags) для страниц с [M]
    footer_register:   list[tuple] = []  # (slug, _Flags) для страниц с [F]
    about_us_register: list[str]   = []  # slug страниц с [W]
    news_page_register: list[str]  = []  # slug страниц с [N]
    slug_errors:       list[str]   = []

    # Домашняя страница — корневой index, вне меню
    pages.append(_page("index", None, _find_content_file(spec_dir)))

    # Обход вложенных папок
    for subdir in sorted(d for d in spec_dir.iterdir() if d.is_dir()):
        _walk(subdir, None, _Flags(), pages, menu_register, footer_register,
              about_us_register, news_page_register, slug_errors)

    pages.append(_page("sitemap", None, None, categories=["Utility Pages"], template="page-sitemap.php"))

    main_nodes   = _build_main_menu(menu_register, pages)
    footer_nodes = _build_footer_menu(footer_register)
    footer_nodes.append(_node("sitemap"))

    return {
        "structure_type": "FSR_2026",
        "pages":          pages,
        "menus":          {"main": main_nodes, "footer": footer_nodes},
        "about_us_slug":  about_us_register[-1] if about_us_register else None,
        "has_news_page":  bool(news_page_register),
        "required_items": _required_items(spec_dir),
        "slug_errors":    slug_errors,
    }


# ── Обход дерева ──────────────────────────────────────────────────────────────

def _walk(
    folder:              Path,
    parent_slug:         str | None,
    inherited_flags:     _Flags,
    pages:               list[dict],
    menu_register:       list[tuple],
    footer_register:     list[tuple],
    about_us_register:   list[str],
    news_page_register:  list[str],
    slug_errors:         list[str],
) -> None:
    index_file = _find_content_file(folder)
    subdirs    = sorted(d for d in folder.iterdir() if d.is_dir())

    if not index_file and not subdirs:
        return

    raw_slug = _extract_slug(folder.name)
    if not index_file and _RE_INVALID.search(raw_slug):
        return  # silently skip staging/work folders (NEW, PROMTS, IMAGES, etc.)

    raw_flags = _parse_flags(folder.name)
    flags     = _merge_flags(inherited_flags, raw_flags)
    slug      = _validate_slug(raw_slug, folder, slug_errors)

    if not slug:
        return

    if index_file:
        categories = ["Utility Pages"] if flags.utility else []
        if flags.about_us:
            categories.append("Who We Are")
        if flags.news_page:
            template = "page-list.php"
            news_page_register.append(slug)
        elif flags.list_page:
            template = "page-list.php"
        else:
            template = "page.php"
        pages.append(_page(slug, parent_slug, index_file,
                           categories=categories,
                           template=template,
                           publish_at=flags.publish_at))
        if flags.about_us:
            about_us_register.append(slug)
        if flags.in_main:
            menu_register.append((slug, flags))
        if flags.in_footer:
            footer_register.append((slug, flags))
        # Дочерние папки — обычный обход без наследования флагов
        for child in subdirs:
            _walk(child, slug, _Flags(), pages, menu_register, footer_register,
                  about_us_register, news_page_register, slug_errors)

    elif subdirs:
        # Контейнер без index: дети продвигаются наверх, наследуя флаги
        for child in subdirs:
            _walk(child, parent_slug, flags, pages, menu_register, footer_register,
                  about_us_register, news_page_register, slug_errors)

    else:
        print(f"  [!] Папка пропущена (нет контента): {folder.name}")


# ── Построение меню ───────────────────────────────────────────────────────────

def _build_main_menu(menu_register: list[tuple], pages: list[dict]) -> list[dict]:
    # Индекс иерархии: parent_slug → [child_slug, ...]
    children_of: dict[str | None, list[str]] = {}
    for p in pages:
        children_of.setdefault(p["parent"], []).append(p["slug"])

    # Сортируем: с явным order — по возрастанию, без order — в конец (порядок обнаружения)
    ordered = sorted(menu_register, key=lambda x: (x[1].m_order is None, x[1].m_order or 0))

    nodes = []
    for slug, flags in ordered:
        node = _node(slug, menu_title=flags.m_label)
        if flags.m_depth > 0:
            kids = _collect_children(slug, children_of, flags.m_depth)
            if kids:
                node["children"] = kids
        nodes.append(node)
    return nodes


def _build_footer_menu(footer_register: list[tuple]) -> list[dict]:
    ordered = sorted(footer_register, key=lambda x: (x[1].f_order is None, x[1].f_order or 0))
    return [_node(slug, menu_title=flags.f_label) for slug, flags in ordered]


def _collect_children(parent_slug: str, children_of: dict, depth: int) -> list[dict]:
    if depth <= 0:
        return []
    nodes = []
    for slug in children_of.get(parent_slug, []):
        node      = _node(slug)
        grandkids = _collect_children(slug, children_of, depth - 1)
        if grandkids:
            node["children"] = grandkids
        nodes.append(node)
    return nodes


# ── DLY / publish_at ─────────────────────────────────────────────────────────

def _resolve_publish_at(dly_value: str | None) -> str:
    if dly_value is None:
        return "schedule"
    try:
        if "T" in dly_value:
            date_str, time_str = dly_value.split("T", 1)
            h, m, s = (int(x) for x in time_str.split("."))
            y, mo, d = (int(x) for x in date_str.split("-"))
            return datetime(y, mo, d, h, m, s).isoformat()
        else:
            y, mo, d = (int(x) for x in dly_value.split("-"))
            return datetime(y, mo, d,
                            random.randint(7, 21),
                            random.randint(0, 59),
                            random.randint(0, 59)).isoformat()
    except Exception as e:
        print(f"  [!] Некорректная дата '[DLY={dly_value}]': {e}. Используется случайная.")
        return "schedule"


# ── Обязательные элементы ─────────────────────────────────────────────────────

def _required_items(spec_dir: Path) -> list:
    _exts      = [".png", ".webp", ".jpg", ".jpeg", ".svg"]
    images_dir = spec_dir / "IMAGES"
    return [
        [spec_dir / "index.html", spec_dir / "index.md"],
        [spec_dir   / f"logo{e}" for e in _exts] + [images_dir / f"logo{e}" for e in _exts],
        [spec_dir   / f"favicon{e}" for e in _exts] + [spec_dir   / f"icon{e}" for e in _exts]
        + [images_dir / f"favicon{e}" for e in _exts] + [images_dir / f"icon{e}" for e in _exts],
    ]
