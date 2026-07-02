import re
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from difflib import get_close_matches
from pathlib import Path
from typing import Dict, List

import requests
from bs4 import BeautifulSoup

_PLACEHOLDER  = re.compile(r'%%PAGEURL:([^%]+)%%')
_FUZZY_CUTOFF = 0.82


# ── Внешние ссылки ────────────────────────────────────────────────────────────

def check_links_in_articles(articles_list: List[Dict], max_workers: int = 10):
    broken_links_map = {}
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                      'AppleWebKit/537.36 (KHTML, like Gecko) '
                      'Chrome/91.0.4472.124 Safari/537.36'
    }

    print(f"\nЗапуск многопоточной проверки ссылок ({max_workers} потоков)...")

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_article = {}

        for article in articles_list:
            wp_block      = article.get('wp_block')
            resource_path = article.get('resource')
            if not wp_block or not resource_path:
                continue

            soup  = BeautifulSoup(wp_block, 'html.parser')
            links = {
                a['href'] for a in soup.find_all('a', href=True)
                if a['href'].startswith(('http://', 'https://'))
            }
            for link in links:
                future = executor.submit(_check_single_link, link, headers)
                future_to_article[future] = resource_path

        completed_count = 0
        total_futures   = len(future_to_article)

        for future in as_completed(future_to_article):
            completed_count += 1
            resource_path = future_to_article[future]
            result        = future.result()

            if result:
                short = _shorten_path(resource_path)
                broken_links_map.setdefault(short, set()).add(result)

            print(f"\rПрогресс: {completed_count}/{total_futures} ссылок проверено", end="")

    print("\r" + " " * 50 + "\r", end="")

    if not broken_links_map:
        print("Битых ссылок не найдено.")
        return

    print("\n- Найдены битые ссылки:")
    for path_str, links in sorted(broken_links_map.items()):
        print(f"    - {path_str}")
        for link in sorted(links):
            print(f"        {link}")

    while True:
        choice = input("\nИгнорировать? ( y / n ) ").lower().strip()
        if choice == 'y':
            return
        elif choice == 'n':
            raise RuntimeError("Проверка внешних ссылок прервана пользователем")


def _check_single_link(link: str, headers: dict) -> str | None:
    try:
        response = requests.head(link, timeout=10, allow_redirects=True, headers=headers)
        if response.status_code >= 400:
            response = requests.get(link, timeout=10, allow_redirects=True, headers=headers)
        if response.status_code >= 400:
            return f"[{response.status_code}] {link}"
    except requests.exceptions.RequestException:
        return f"[Connection Error] {link}"
    return None


def _shorten_path(full_path: Path) -> str:
    try:
        parts      = full_path.parts
        spec_index = parts.index('spec')
        start      = spec_index - 1 if spec_index > 0 else spec_index
        return Path(*parts[start:]).as_posix()
    except (ValueError, IndexError):
        if len(full_path.parts) > 3:
            return Path(*full_path.parts[-3:]).as_posix()
        return full_path.as_posix()


# ── Внутренние ссылки ─────────────────────────────────────────────────────────

def check_internal_links(manifest: dict, staging_dir: Path) -> None:
    page_slugs = {p['slug'] for p in manifest.get('pages', [])}

    image_stems: set[str] = set()
    for p in manifest.get('pages', []):
        for img in p.get('images', []):
            fname = img["filename"] if isinstance(img, dict) else img
            image_stems.add(Path(fname).stem)
    for key in ('favicon', 'logo'):
        val = manifest.get('site', {}).get(key)
        if val:
            image_stems.add(Path(val).stem)

    pages_dir = staging_dir / 'pages'
    errors: list[str] = []
    fixed:  list[str] = []
    total = ok = 0

    for wp_file in sorted(pages_dir.glob('*.wp')):
        content     = wp_file.read_text(encoding='utf-8')
        new_content = content

        for match in _PLACEHOLDER.finditer(content):
            slug  = match.group(1)
            total += 1

            if slug in page_slugs:
                ok += 1
                continue

            if slug in image_stems:
                errors.append(f"{wp_file.name}: '{slug}' — имя картинки, не страницы!")
                continue

            candidates = get_close_matches(slug, page_slugs, n=2, cutoff=_FUZZY_CUTOFF)
            if len(candidates) == 1:
                best        = candidates[0]
                new_content = new_content.replace(f'%%PAGEURL:{slug}%%', f'%%PAGEURL:{best}%%')
                fixed.append(f"{wp_file.name}: '{slug}' → '{best}'")
                ok += 1
            else:
                hints    = get_close_matches(slug, page_slugs, n=3, cutoff=0.5)
                hint_str = f"\n  Похожие:\n    {',\n    '.join(hints)}\n" if hints else ""
                errors.append(f"{wp_file.name}: slug '{slug}' не найден в манифесте.{hint_str}")

        if new_content != content:
            wp_file.write_text(new_content, encoding='utf-8')

    if fixed:
        print(f"\n  Авто-исправлено ({len(fixed)}):")
        for item in fixed:
            print(f"  {item}")

    if errors:
        print(f"\n  Ошибки ({len(errors)}):")
        for e in errors:
            print(f"  {e}")
        print(
            "\n  Исправить ссылки в spec/ можно с помощью modlinks.py:\n"
            "    uv run modlinks.py rep <slug> <new-slug>   — заменить слаг\n"
            "    uv run modlinks.py rm  <slug>              — убрать ссылку (оставить текст)"
        )
        raise RuntimeError("Проверка внутренних ссылок не пройдена")

    if total == 0:
        print("  Внутренних ссылок не найдено.")
    else:
        auto = f", авто-исправлено: {len(fixed)}" if fixed else ""
        print(f"  Внутренних ссылок: {ok}/{total} корректны{auto}.")
