import re
from pathlib import Path
from urllib.parse import urlparse
from bs4 import BeautifulSoup

from ..generate_slug import slug_from_pages_list, advanced_slugify


def _find_content_file(cl_dir: Path) -> Path | None:
    """Ищет контентный файл в директории: сначала .html, потом .md."""
    for name in ("index.html", "index.md"):
        if (cl_dir / name).exists():
            return cl_dir / name
    for pattern in ("*.html", "*.md"):
        found = list(cl_dir.glob(pattern))
        if found:
            return found[0]
    return None


def _slug_from_url(url: str) -> str | None:
    segments = [s for s in urlparse(url).path.split("/") if s]
    return segments[-1] if segments else None


def _canonical_slug(file: Path) -> str | None:
    """Извлекает slug из canonical URL файла (HTML или MD)."""
    try:
        content = file.read_text(encoding="utf-8")
        if file.suffix == ".md":
            m = re.match(r"^---\n(.*?)\n---", content, re.DOTALL)
            if m:
                for line in m.group(1).splitlines():
                    if line.startswith("canonical:"):
                        url = line.partition(":")[2].strip().strip("\"'")
                        return _slug_from_url(url) if url else None
        else:
            tag = BeautifulSoup(content, "html.parser").find("link", rel="canonical")
            if tag and tag.get("href"):
                return _slug_from_url(tag["href"])
    except Exception:
        pass
    return None


def _cl_slug(cl_dir: Path, fallback: str) -> str:
    content_file = _find_content_file(cl_dir)

    # 1. canonical URL → последний сегмент пути
    if content_file:
        slug = _canonical_slug(content_file)
        if slug:
            return slug

    # 2. pages-list.txt (только для HTML)
    if content_file and content_file.suffix == ".html":
        slug = slug_from_pages_list(content_file)
        if slug:
            return slug

    # 3. h1 → advanced_slugify
    if content_file and content_file.exists():
        try:
            text = None
            if content_file.suffix == ".md":
                for line in content_file.read_text(encoding="utf-8").splitlines():
                    if line.startswith("# "):
                        text = line[2:].strip()
                        break
            else:
                soup = BeautifulSoup(content_file.read_text(encoding="utf-8"), "html.parser")
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
