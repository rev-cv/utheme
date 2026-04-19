from pathlib import Path


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
