"""
Structure 1 — FLAT5: PILLAR + CL1-CL5 at spec root.
Optionally combined with CLUSTERS ADD/CL1-CL30 for scheduled content.
"""
from pathlib import Path

from ._shared import _page, _node, _cl_slug


def detect(spec_dir: Path) -> bool:
    return (spec_dir / "PILLAR").is_dir() and (spec_dir / "CL1").is_dir()


def build(spec_dir: Path) -> dict:
    pages = _pages_flat5(spec_dir)
    has_add = (spec_dir / "CLUSTERS ADD").is_dir()
    if has_add:
        pages += _pages_clusters_add30(spec_dir)
    return {
        "structure_type": "flat5",
        "pages":          pages,
        "menus":          _build_menus(pages),
        "required_items": _required_items(spec_dir, has_add),
    }


# ─── Page builders ───────────────────────────────────────────────────────────

def _pages_flat5(spec_dir: Path) -> list[dict]:
    pages = [
        _page("index",    None, spec_dir / "PILLAR"),
        _page("articles", None, None, categories=["Utility Pages"], template="page-list.php"),
        _page("news",     None, None, categories=["Utility Pages"], template="page-list.php"),
        _page("sitemap",  None, None, categories=["Utility Pages"], template="page-sitemap.php"),
    ]
    for i in range(1, 6):
        cl_dir = spec_dir / f"CL{i}"
        if cl_dir.is_dir():
            slug = _cl_slug(cl_dir, f"cl{i}")
            pages.append(_page(slug, "articles", cl_dir, categories=["page+5"]))
    return pages + _add_pages(spec_dir)


def _pages_clusters_add30(spec_dir: Path) -> list[dict]:
    pages = []
    clusters_dir = spec_dir / "CLUSTERS ADD"
    for i in range(1, 31):
        cl_dir = clusters_dir / f"CL{i}"
        if cl_dir.is_dir():
            slug = _cl_slug(cl_dir, f"cl{i}")
            pages.append(_page(slug, "articles", cl_dir, categories=["page+30"], publish_at="schedule"))
    return pages


def _add_pages(spec_dir: Path) -> list[dict]:
    add = spec_dir / "ADD PAGES"
    return [
        _page("about-us",       None, add / "about-us.html",       categories=["Utility Pages"]),
        _page("cookie-policy",  None, add / "cookie-policy.html",  categories=["Utility Pages"]),
        _page("privacy-policy", None, add / "privacy-policy.html", categories=["Utility Pages"]),
        _page("legal-notice",   None, add / "legal-notice.html",   categories=["Utility Pages"]),
    ]


# ─── Helpers ─────────────────────────────────────────────────────────────────

def _build_menus(pages: list[dict]) -> dict:
    main_nodes = [
        _node(p["slug"])
        for p in pages
        if p.get("parent") == "articles" and "page+5" in p.get("categories", [])
    ]
    return {
        "main": main_nodes + [_node("articles"), _node("news")],
        "footer": [
            _node("about-us"),
            _node("cookie-policy"),
            _node("privacy-policy"),
            _node("legal-notice"),
            _node("sitemap"),
        ],
    }


def _required_items(spec_dir: Path, has_add: bool) -> list:
    _exts = [".png", ".webp", ".jpg", ".jpeg", ".svg"]
    items = [
        spec_dir / "PILLAR",
        spec_dir / "ADD PAGES",
        spec_dir / "ADD PAGES" / "about-us.html",
        spec_dir / "ADD PAGES" / "legal-notice.html",
        spec_dir / "ADD PAGES" / "privacy-policy.html",
        spec_dir / "ADD PAGES" / "cookie-policy.html",
        [spec_dir / f"logo{e}"    for e in _exts],
        [spec_dir / f"favicon{e}" for e in _exts] + [spec_dir / f"icon{e}" for e in _exts],
        *[spec_dir / f"CL{i}" for i in range(1, 6)],
    ]
    if has_add:
        add = spec_dir / "CLUSTERS ADD"
        items.append(add)
        items += [add / f"CL{i}" for i in range(1, 31)]
    return items
