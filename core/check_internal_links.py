import re
import sys
from difflib import get_close_matches
from pathlib import Path

_PLACEHOLDER = re.compile(r'%%PAGEURL:([^%]+)%%')
_FUZZY_CUTOFF = 0.82  # минимальное сходство для авто-исправления опечатки


def check_internal_links(manifest: dict, staging_dir: Path) -> None:
    """Validates %%PAGEURL:slug%% placeholders in .wp files against manifest.

    Auto-fixes single-candidate typos in-place. Stops on image-name conflicts
    or unresolvable slugs.
    """
    page_slugs = {p['slug'] for p in manifest.get('pages', [])}

    image_stems: set[str] = set()
    for p in manifest.get('pages', []):
        for img in p.get('images', []):
            image_stems.add(Path(img).stem)
    for key in ('favicon', 'logo'):
        val = manifest.get('site', {}).get(key)
        if val:
            image_stems.add(Path(val).stem)

    pages_dir = staging_dir / 'pages'
    errors: list[str] = []
    fixed: list[str] = []
    total = ok = 0

    for wp_file in sorted(pages_dir.glob('*.wp')):
        content = wp_file.read_text(encoding='utf-8')
        new_content = content

        for match in _PLACEHOLDER.finditer(content):
            slug = match.group(1)
            total += 1

            if slug in page_slugs:
                ok += 1
                continue

            if slug in image_stems:
                errors.append(
                    f"{wp_file.name}: '{slug}' — имя картинки, не страницы!"
                )
                continue

            # Fuzzy match: один явный кандидат → авто-исправление
            candidates = get_close_matches(slug, page_slugs, n=2, cutoff=_FUZZY_CUTOFF)
            if len(candidates) == 1:
                best = candidates[0]
                new_content = new_content.replace(
                    f'%%PAGEURL:{slug}%%', f'%%PAGEURL:{best}%%'
                )
                fixed.append(f"{wp_file.name}: '{slug}' → '{best}'")
                ok += 1
            else:
                hints = get_close_matches(slug, page_slugs, n=3, cutoff=0.5)
                hint_str = f"\n  Похожие:\n    {',\n    '.join(hints)}\n" if hints else ""
                errors.append(
                    f"{wp_file.name}: slug '{slug}' не найден в манифесте.{hint_str}"
                )

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
        sys.exit(1)

    if total == 0:
        print("  Внутренних ссылок не найдено.")
    else:
        auto = f", авто-исправлено: {len(fixed)}" if fixed else ""
        print(f"  Внутренних ссылок: {ok}/{total} корректны{auto}.")
