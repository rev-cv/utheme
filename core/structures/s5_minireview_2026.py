"""
Structure 5 — MINIREVIEW_2026
==============================

ОПИСАНИЕ
--------
Вариант FSR_2026, в котором домашняя страница (hub) живёт в подпапке hub/,
а не в корне spec/. Все остальные папки маршрутизируются как в FSR_2026.

ОБНАРУЖЕНИЕ (detect)
--------------------
Активируется при одновременном соблюдении четырёх условий:
  • В корне spec/ НЕТ index.html / index.md        — отличает от FSR_2026
  • В корне spec/ есть папка hub/ с контентным файлом
  • В корне нет папки PILLAR/                      — исключает CL5_2025/2026
  • В корне нет папки HUB/ (uppercase)             — исключает FWC_2026

СТРУКТУРА ПАПОК
---------------
  spec/
    hub/
      index.html          ← домашняя страница (slug="index")
      images/             ← игнорируется (нет index-файла)
    karty/                ← контейнер (нет index) → дети поднимаются наверх
      visa/
        index2.html       ← страница slug="visa", parent=None
      mastercard/
        index3.html       ← страница slug="mastercard", parent=None
    bankovni-prevod/
      index17.html        ← страница slug="bankovni-prevod", parent=None
      vyber-na-ucet/
        index21.html      ← страница slug="vyber-na-ucet", parent="bankovni-prevod"
    logo.webp             ← обязательно
    favicon.webp          ← обязательно

ФЛАГИ
-----
Те же, что в FSR_2026: [M], [F], [U], [DLY], со всеми вариациями.
Применяются к именам папок (кроме hub/).
"""

import re
import random
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path

from ._shared import _find_content_file, _node, _page


# ── Регулярные выражения ──────────────────────────────────────────────────────

_RE_M = re.compile(r'\[(\d*)M(\d*)(?:;([^\]]*))?\]')
_RE_F = re.compile(r'\[(\d*)F(?:;([^\]]*))?\]')
_RE_U = re.compile(r'\[U\]')
_RE_W = re.compile(r'\[W\]')
_RE_A = re.compile(r'\[A\]')
_RE_N = re.compile(r'\[N\]')
_RE_DLY = re.compile(r'\[DLY(?:=([^\]]+))?\]')
_RE_ANY_FLAG = re.compile(
    r'\[\d*M\d*(?:;[^\]]*)?\]'
    r'|\[\d*F(?:;[^\]]*)?\]'
    r'|\[U\]'
    r'|\[W\]'
    r'|\[A\]'
    r'|\[N\]'
    r'|\[DLY(?:=[^\]]*)?\]'
)
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
    no_root_index = (
        not (spec_dir / "index.html").exists()
        and not (spec_dir / "index.md").exists()
    )
    hub_dir       = spec_dir / "hub"
    has_hub       = hub_dir.is_dir() and _find_content_file(hub_dir) is not None
    dir_names     = {d.name for d in spec_dir.iterdir() if d.is_dir()}
    no_pillar     = "PILLAR" not in dir_names
    no_HUB        = "HUB" not in dir_names   # case-sensitive: hub/ ≠ HUB/
    return no_root_index and has_hub and no_pillar and no_HUB


def build(spec_dir: Path) -> dict:
    pages:              list[dict]  = []
    menu_register:      list[tuple] = []
    footer_register:    list[tuple] = []
    about_us_register:  list[str]   = []
    news_page_register: list[str]   = []
    slug_errors:        list[str]   = []

    # Домашняя страница — hub/index.html, вне меню
    hub_dir = spec_dir / "hub"
    pages.append(_page("index", None, _find_content_file(hub_dir)))

    # Обход вложенных папок, кроме hub/ (она уже потреблена как homepage)
    for subdir in sorted(d for d in spec_dir.iterdir() if d.is_dir() and d.name != "hub"):
        _walk(subdir, None, _Flags(), pages, menu_register, footer_register,
              about_us_register, news_page_register, slug_errors)

    pages.append(_page("sitemap", None, None, categories=["Utility Pages"], template="page-sitemap.php"))

    main_nodes   = _build_main_menu(menu_register, pages)
    footer_nodes = _build_footer_menu(footer_register)
    footer_nodes.append(_node("sitemap"))

    return {
        "structure_type": "MINIREVIEW_2026",
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
    raw_flags = _parse_flags(folder.name)
    flags     = _merge_flags(inherited_flags, raw_flags)
    slug      = _validate_slug(_extract_slug(folder.name), folder, slug_errors)

    if not slug:
        return

    index_file = _find_content_file(folder)
    subdirs    = sorted(d for d in folder.iterdir() if d.is_dir())

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
    children_of: dict[str | None, list[str]] = {}
    for p in pages:
        children_of.setdefault(p["parent"], []).append(p["slug"])

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
    _exts  = [".png", ".webp", ".jpg", ".jpeg", ".svg"]
    hub_dir = spec_dir / "hub"
    return [
        [hub_dir / "index.html", hub_dir / "index.md"],
        [spec_dir / f"logo{e}"    for e in _exts],
        [spec_dir / f"favicon{e}" for e in _exts] + [spec_dir / f"icon{e}" for e in _exts],
    ]
