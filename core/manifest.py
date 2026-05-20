import os
from pathlib import Path

from core import branding
from core.translations import resolve_locale

REQUIRED_PAGE_FIELDS = ["slug", "title", "publish_at"]


def site_config(spec_dir: Path) -> dict:
    favicon_path = branding.find_branding_file(spec_dir, ["favicon", "icon"])
    logo_path    = branding.find_branding_file(spec_dir, ["logo"])
    return {
        "title":      os.environ.get("SITE_TITLE",  "WordPress Site"),
        "url":        os.environ.get("SITE_URL",     "http://localhost:8080"),
        "wp_locale":  resolve_locale(os.environ.get("SITE_LANG", "EN"))[0],
        "lang":       resolve_locale(os.environ.get("SITE_LANG", "EN"))[1],
        "email":      os.environ.get("ADMIN_EMAIL",  "admin@example.com"),
        "admin_user": os.environ.get("ADMIN_USER",   "admin"),
        "favicon":    Path(favicon_path).name if favicon_path else None,
        "logo":       Path(logo_path).name    if logo_path    else None,
    }


_BRANDING_STEMS = {'logo', 'favicon', 'icon'}


def _link_images(articles: list[dict], pics: list[dict]) -> list[dict]:
    content_images = [
        img for img in pics
        if Path(str(img.get('selected_image', ''))).stem.lower() not in _BRANDING_STEMS
    ]
    result = []
    for article in articles:
        copy = article.copy()
        copy['images'] = [img for img in content_images if img.get('html') == article.get('resource')]
        result.append(copy)
    return result


def build_manifest(structure: dict, pics: list[dict], spec_dir: Path) -> dict:
    from core import extract_meta as extraction
    from core import enrich_with_schedule as schedule
    from core import translations

    _, lang = resolve_locale(os.environ.get("SITE_LANG", "EN"))
    pages = structure["pages"]

    content_pages    = [p for p in pages if p.get("content")]
    structural_pages = [p for p in pages if not p.get("content")]

    resource_list = [{"resource": p["content"], **p} for p in content_pages]
    resource_list = extraction.resolve_resource_paths(spec_dir, resource_list)
    resource_list = extraction.fetch_meta_data(resource_list)
    resource_list = _link_images(resource_list, pics)

    for item in resource_list:
        resource_path = Path(item["resource"])
        # schema_file = resource_path.parent / "schema.php"
        # if schema_file.exists():
        #     item["schema_html"] = schema_file.read_text(encoding="utf-8").strip()

        slug = item["slug"]
        item["content"]   = f"{slug}.wp"
        item["title"]     = item.get("title") or item.get("h1") or slug
        item["seo_title"] = item.get("headline") or item.get("title")
        item["seo_descr"] = item.get("description")
        item["images"]    = [
            Path(img["selected_image"]).name
            for img in item.get("images", [])
            if img.get("selected_image")
        ]
        for field in ("resource", "h1", "headline", "description"):
            item.pop(field, None)

    site_title = os.environ.get("SITE_TITLE", "")
    for item in structural_pages:
        slug = item["slug"]
        item["title"]     = translations.get_page_title(slug, lang)
        item["seo_title"] = translations.get_page_seo_title(slug, lang)
        item["seo_descr"] = translations.get_page_description(slug, lang, site_title)

    content_map    = {p["slug"]: p for p in resource_list}
    structural_map = {p["slug"]: p for p in structural_pages}
    all_pages = [
        content_map.get(p["slug"]) or structural_map.get(p["slug"])
        for p in pages
    ]

    scheduled = [p for p in all_pages if p.get("publish_at") == "schedule"]
    if scheduled:
        pattern      = os.environ.get("SCHEDULE_PATTERN", "3d 2-3p (10-21)")
        scheduled    = schedule.enrich_with_schedule(scheduled, pattern)
        schedule_map = {p["slug"]: p["publish_at"] for p in scheduled}
        for item in all_pages:
            if item["slug"] in schedule_map:
                item["publish_at"] = schedule_map[item["slug"]].strftime("%Y-%m-%dT%H:%M:%S")

    return {
        "site":           site_config(spec_dir),
        "structure_type": structure["structure_type"],
        "menus":          structure["menus"],
        "pages":          all_pages,
    }


def validate_manifest(manifest: dict, staging_dir: Path) -> None:
    errors = []

    if not manifest.get("site", {}).get("title"):
        errors.append("site.title отсутствует")

    slugs = set()
    for i, page in enumerate(manifest.get("pages", [])):
        prefix = f"pages[{i}] slug={page.get('slug', '?')!r}"
        for field in REQUIRED_PAGE_FIELDS:
            if not page.get(field):
                errors.append(f"{prefix}: отсутствует поле '{field}'")
        slug = page.get("slug")
        if slug in slugs:
            errors.append(f"{prefix}: дублирующийся slug")
        slugs.add(slug)
        content = page.get("content")
        if content:
            content_path = staging_dir / "pages" / content
            if not content_path.exists():
                errors.append(f"{prefix}: .wp файл не найден ({content_path})")

    if errors:
        print("\n  ОШИБКИ В МАНИФЕСТЕ:")
        for e in errors:
            print(f"    ✗ {e}")
        raise RuntimeError("Манифест не прошёл валидацию")

    print(f"  Манифест валиден. Страниц: {len(manifest['pages'])}")
