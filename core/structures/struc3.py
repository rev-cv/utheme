"""
Structure 3 — HUB: filesystem-driven hub + sections structure.

Layout:
  HUB/PILLAR/index.html        → home page (/)
  TITLE - slug/                → section page (no parent, goes in main menu)
    slug.html                  → section content
    child-slug/                → child article (parent = section slug)
      child-slug.html
      _dynamic.txt             → if present with no html: create as draft only
  ADD PAGES/
    page.html                  → utility page (no parent, footer only)
    TITLE - slug/              → utility page (no parent, appended to main menu)
      slug.html

Menu: section pages → ADD PAGES TITLE-slug pages → [articles / news in footer]
Menu labels for TITLE-slug pages come from the TITLE part of the folder name.
"""
from pathlib import Path

from ._shared import _page, _node

_SKIP_DIRS = {"HUB"}


def detect(spec_dir: Path) -> bool:
    return (spec_dir / "HUB" / "PILLAR").is_dir()


def build(spec_dir: Path) -> dict:
    pages:        list[dict] = []
    main_nodes:   list[dict] = []
    footer_nodes: list[dict] = []

    hub_html = spec_dir / "HUB" / "PILLAR" / "index.html"
    pages += [
        _page("index",   None, hub_html if hub_html.exists() else None),
        _page("news",    None, None, categories=["Utility Pages"], template="page-list.php"),
        _page("sitemap", None, None, categories=["Utility Pages"], template="page-sitemap.php"),
    ]

    add_pages_dir = spec_dir / "ADD PAGES"

    # ── Section pages (top-level TITLE - slug dirs, excluding HUB / ADD PAGES) ─
    for top_dir in sorted(spec_dir.iterdir()):
        if not top_dir.is_dir():
            continue
        if top_dir.name in _SKIP_DIRS or top_dir == add_pages_dir:
            continue
        if " - " not in top_dir.name:
            continue

        menu_title, slug = top_dir.name.split(" - ", 1)
        section_html = top_dir / f"{slug}.html"
        pages.append(_page(slug, None, section_html if section_html.exists() else None))
        main_nodes.append(_node(slug, menu_title=menu_title))

        # Child articles inside the section dir
        child_nodes: list[dict] = []
        for child_dir in sorted(top_dir.iterdir()):
            if not child_dir.is_dir():
                continue
            child_slug = child_dir.name
            child_html = child_dir / f"{child_slug}.html"

            if not child_html.exists():
                # No HTML → create draft placeholder (e.g. _dynamic.txt present)
                pages.append(_page(child_slug, slug, None, post_status="draft"))
            else:
                pages.append(_page(child_slug, slug, child_html))
                child_nodes.append(_node(child_slug))

        if child_nodes:
            main_nodes[-1]["children"] = child_nodes

    # ── ADD PAGES ─────────────────────────────────────────────────────────────
    if add_pages_dir.is_dir():
        for item in sorted(add_pages_dir.iterdir()):
            if item.is_file() and item.suffix == ".html" and item.stem != "404":
                pages.append(_page(item.stem, None, item, categories=["Utility Pages"]))
                footer_nodes.append(_node(item.stem))
            elif item.is_dir() and " - " in item.name:
                menu_title, slug = item.name.split(" - ", 1)
                sub_html = item / f"{slug}.html"
                pages.append(_page(
                    slug, None,
                    sub_html if sub_html.exists() else None,
                    categories=["Utility Pages"],
                ))
                main_nodes.append(_node(slug, menu_title=menu_title))

    footer_nodes.append(_node("sitemap"))

    return {
        "structure_type": "hub_pillar",
        "pages":          pages,
        "menus":          {"main": main_nodes, "footer": footer_nodes},
        "required_items": _required_items(spec_dir),
    }


def _required_items(spec_dir: Path) -> list:
    _exts = [".png", ".webp", ".jpg", ".jpeg", ".svg"]
    hub_pillar = spec_dir / "HUB" / "PILLAR"
    return [
        hub_pillar,
        hub_pillar / "index.html",
        spec_dir / "ADD PAGES",
        [spec_dir / f"logo{e}"    for e in _exts],
        [spec_dir / f"favicon{e}" for e in _exts] + [spec_dir / f"icon{e}" for e in _exts],
    ]
