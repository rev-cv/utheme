from pathlib import Path
from bs4 import BeautifulSoup

from ..generate_slug import slug_from_pages_list, advanced_slugify


def _cl_slug(cl_dir: Path, fallback: str) -> str:
    html_file = cl_dir / "index.html"
    if not html_file.exists():
        found = list(cl_dir.glob("*.html"))
        html_file = found[0] if found else None

    slug = slug_from_pages_list(html_file) if html_file else None
    if slug:
        return slug

    if html_file and html_file.exists():
        try:
            soup = BeautifulSoup(html_file.read_text(encoding="utf-8"), "html.parser")
            h1 = soup.find("h1")
            text = h1.get_text(strip=True) if h1 else None
            if not text:
                title = soup.find("title")
                text = title.get_text(strip=True) if title else None
            if text:
                return advanced_slugify(text)
        except Exception:
            pass

    return fallback


def _page(
    slug:        str,
    parent:      str | None,
    content:     Path | None,
    categories:  list[str] | None = None,
    publish_at:  str = "now",
    template:    str = "page.php",
    post_status: str | None = None,
) -> dict:
    return {
        "slug":        slug,
        "parent":      parent,
        "content":     str(content) if content else None,
        "template":    template,
        "categories":  categories or [],
        "images":      [],
        "title":       None,
        "seo_title":   None,
        "seo_descr":   None,
        "publish_at":  publish_at,
        "post_status": post_status,
    }


def _node(slug: str, children: list | None = None, menu_title: str | None = None) -> dict:
    node: dict = {"slug": slug}
    if children:
        node["children"] = children
    if menu_title:
        node["menu_title"] = menu_title
    return node
