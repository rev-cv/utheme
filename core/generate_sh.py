import base64
import json
import os
from pathlib import Path
from jinja2 import Environment, FileSystemLoader

from .translations import (
    EDITORIAL, WELCOME_NEWS_TITLE, WELCOME_NEWS_CONTENT,
    get_editorial_name,
)
from core.console import action, result

_TEMPLATES_DIR = Path(__file__).parent.parent / "templates"


def _bash_escape(s: str) -> str:
    s = str(s) if s is not None else ""
    s = s.replace("\\", "\\\\")
    s = s.replace('"', '\\"')
    s = s.replace("`", "\\`")
    s = s.replace("$", "\\$")
    return s


def _b64(s: str) -> str:
    s = str(s) if s is not None else ""
    return base64.b64encode(s.encode("utf-8")).decode("ascii")


def generate_sh(manifest: dict, out_path: Path) -> None:
    action("Генерация provision.sh и robots.txt")

    lang       = manifest["site"]["lang"]
    site_title = manifest["site"]["title"]

    env = Environment(
        loader=FileSystemLoader(str(_TEMPLATES_DIR)),
        trim_blocks=True,
        lstrip_blocks=True,
        keep_trailing_newline=True,
    )
    env.filters["bash_escape"] = _bash_escape
    env.filters["b64"] = _b64

    template = env.get_template("provision.sh.j2")

    welcome_title   = WELCOME_NEWS_TITLE.get(lang) or WELCOME_NEWS_TITLE["EN"]
    welcome_content = (WELCOME_NEWS_CONTENT.get(lang) or WELCOME_NEWS_CONTENT["EN"]).format(
        site_title=site_title
    )

    ctx = {
        "site":            manifest["site"],
        "pages":           manifest["pages"],
        "menus":           manifest.get("menus", {}),
        "wp_locale":       manifest["site"]["wp_locale"],
        "editorial_first": EDITORIAL.get(lang) or EDITORIAL["EN"],
        "editorial":       get_editorial_name(lang, site_title),
        "welcome_title":   welcome_title,
        "welcome_content": welcome_content,
        "has_news_page":   manifest.get("has_news_page", False),
        "is_new":          os.getenv("IS_NEW", "").strip().lower() in ("yes", "1", "true"),
        "image_alts_json": json.dumps(
            {img["filename"]: img["alt"]
             for page in manifest.get("pages", [])
             for img in page.get("images", [])
             if isinstance(img, dict) and img.get("alt")},
            ensure_ascii=False,
        ),
    }

    rendered = template.render(ctx)
    out_path.write_text(rendered, encoding="utf-8", newline="\n")
    try:
        display = out_path.relative_to(Path.cwd()).as_posix()
    except ValueError:
        display = out_path.as_posix()
    result(f"Записан: {display}", style="green")

    robots_tpl = env.get_template("robots.txt.j2")
    robots_out = out_path.parent / "robots.txt"
    robots_out.write_text(robots_tpl.render(ctx), encoding="utf-8", newline="\n")
    try:
        robots_display = robots_out.relative_to(Path.cwd()).as_posix()
    except ValueError:
        robots_display = robots_out.as_posix()
    result(f"Записан: {robots_display}", style="green")
