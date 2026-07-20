import re
from pathlib import Path
from bs4 import BeautifulSoup

from core.console import warn

def _parse_frontmatter(content: str) -> dict:
    """Парсит YAML frontmatter из MD-файла (только flat key: value)."""
    m = re.match(r"^---\n(.*?)\n---", content, re.DOTALL)
    if not m:
        return {}
    data = {}
    for line in m.group(1).splitlines():
        if ":" in line:
            key, _, value = line.partition(":")
            data[key.strip()] = value.strip().strip("\"'")
    return data


def _meta_from_html(content: str) -> dict:
    meta = {"title": "", "description": "", "h1": "", "headline": ""}
    soup = BeautifulSoup(content, "html.parser")

    title_tag = soup.find("title")
    if title_tag:
        meta["title"] = title_tag.get_text(strip=True)
    else:
        warn("Title не обнаружен!")

    h1_tag = soup.find("h1")
    if h1_tag:
        meta["h1"] = h1_tag.get_text(strip=True)
    else:
        warn("H1 не обнаружен!")

    desc_tag = (soup.find("meta", attrs={"name": "description"}) or
                soup.find("meta", attrs={"property": "og:description"}))
    if desc_tag:
        meta["description"] = desc_tag.get("content", "").strip()
    else:
        warn("description не найден!")

    headline_tag = (soup.find("meta", attrs={"name": "headline"}) or
                    soup.find("meta", attrs={"property": "og:title"}) or
                    soup.find("meta", attrs={"name": "twitter:title"}))
    if headline_tag:
        meta["headline"] = headline_tag.get("content", "").strip()

    return meta


def _meta_from_md(content: str) -> dict:
    meta = {"title": "", "description": "", "h1": "", "headline": ""}
    fm = _parse_frontmatter(content)

    meta["title"]       = fm.get("title", "")
    meta["description"] = fm.get("description", "")
    meta["headline"]    = fm.get("headline", "")

    # h1 извлекаем из первого # заголовка в теле файла
    for line in content.splitlines():
        if line.startswith("# "):
            meta["h1"] = line[2:].strip()
            break

    if not meta["title"]:
        meta["title"] = meta["h1"]
    if not meta["h1"]:
        warn("H1 не обнаружен!")
    if not meta["description"]:
        warn("description не найден!")

    return meta


def fetch_meta_data(pages_list: list[dict]) -> list[dict]:
    """
    Принимается список объектов с полем 'resource' (Path к .html или .md файлу).
    Извлекает данные и дополняет объекты полями h1, title, description и headline.
    """
    enriched_list = []

    for item in pages_list:
        new_item = item.copy()
        file_path = item.get("resource")
        meta = {"title": "", "description": "", "h1": "", "headline": ""}

        if isinstance(file_path, Path) and file_path.exists():
            try:
                content = file_path.read_text(encoding="utf-8")
                if file_path.suffix == ".md":
                    meta = _meta_from_md(content)
                else:
                    meta = _meta_from_html(content)
            except Exception as e:
                raise RuntimeError(f"Ошибка чтения {file_path}: {e}") from e

        new_item.update(meta)
        enriched_list.append(new_item)

    return enriched_list


def resolve_resource_paths(base_path: Path, pages_list: list[dict]) -> list[dict]:
    """
    Преобразует строку поля 'resource' в объект Path к конкретному .html файлу.
    Если файл не найден, выводит сообщение и обрывает выполнение скрипта.
    """
    resolved_list = []

    for item in pages_list:
        new_item = item.copy()
        raw_resource = item["resource"]
        resource_path = base_path / raw_resource

        target_file = None

        # если это директория — ищем .html, потом .md
        if resource_path.is_dir():
            for name in ("index.html", "index.md"):
                if (resource_path / name).exists():
                    target_file = resource_path / name
                    break
            else:
                html_files = list(resource_path.glob("*.html"))
                md_files   = list(resource_path.glob("*.md"))
                if html_files:
                    target_file = html_files[0]
                elif md_files:
                    target_file = md_files[0]
                else:
                    target_file = resource_path / "index.html"
        # если файл уже с расширением .html или .md
        elif resource_path.suffix in (".html", ".md"):
            target_file = resource_path
        # если путь без расширения — пробуем .html, потом .md
        else:
            target_file = resource_path.with_suffix(".html")
            if not target_file.exists():
                target_file = resource_path.with_suffix(".md")

        if target_file and target_file.exists():
            new_item["resource"] = target_file
        else:
            raise RuntimeError(f"Файл не найден для '{raw_resource}', ожидался: {target_file}")

        resolved_list.append(new_item)

    return resolved_list


def get_meta(base_path: Path, pages_list: list[dict]) -> list[dict]:
    return enrich_pages_with_meta(base_path, pages_list)
