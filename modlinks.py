#!/usr/bin/env python3
# /// script
# requires-python = ">=3.13"
# dependencies = ["bs4"]
# ///
"""Modify internal links in spec/**/*.html.

Usage:
  modlinks.py rep <slug> <new-slug>   Replace all internal links to <slug> with <new-slug>
  modlinks.py rm  <slug>              Strip <a> tags linking to <slug>, keeping link text
"""

import re
import sys
from pathlib import Path
from urllib.parse import urlparse

from bs4 import BeautifulSoup

SPEC_DIR = Path(__file__).parent / "spec"

_EXTERNAL_SCHEMES = ('http://', 'https://', 'mailto:', 'tel:', '#', '//', 'javascript:')


def _is_internal(href: str) -> bool:
    return not any(href.startswith(s) for s in _EXTERNAL_SCHEMES)


def _href_slug(href: str) -> str:
    path = urlparse(href).path.rstrip('/')
    return path.split('/')[-1] if path else ''


def _replace_slug_in_href(href: str, old: str, new: str) -> str:
    if new == '/':
        return '/'

    parts = href.rstrip('/').rsplit('/', 1)
    if len(parts) == 2:
        return f"{parts[0]}/{new}"
    return new


def _restore_faq_href_quotes(content: str) -> str:
    """Restore single-quoted hrefs inside FAQ shortcode items.

    BeautifulSoup serializes href='...' as href="...", which breaks the outer
    desc="..." attribute in [id=...] shortcode items.
    """
    lines = []
    for line in content.splitlines(keepends=True):
        if '[id=' in line:
            line = re.sub(r'href="([^"]*)"', r"href='\1'", line)
        lines.append(line)
    return ''.join(lines)


def _process_file(html_file: Path, mode: str, old_slug: str, new_slug: str = '') -> bool:
    content = html_file.read_text(encoding='utf-8')
    soup = BeautifulSoup(content, 'html.parser')

    changed = False
    for a_tag in soup.find_all('a', href=True):
        href = a_tag['href']
        if not _is_internal(href):
            continue
        if _href_slug(href) != old_slug:
            continue

        if mode == 'rep':
            a_tag['href'] = _replace_slug_in_href(href, old_slug, new_slug)
            changed = True
        elif mode == 'rm':
            a_tag.replace_with(a_tag.get_text())
            changed = True

    if changed:
        html_file.write_text(_restore_faq_href_quotes(str(soup)), encoding='utf-8')
    return changed


def main() -> None:
    args = sys.argv[1:]

    if len(args) < 2 or args[0] not in ('rep', 'rm'):
        print(__doc__)
        sys.exit(1)

    mode = args[0]
    old_slug = args[1]

    if mode == 'rep':
        if len(args) < 3:
            print("Error: 'rep' requires two slugs.\n")
            print(__doc__)
            sys.exit(1)
        new_slug = args[2]
    else:
        new_slug = ''

    if not SPEC_DIR.exists():
        print(f"spec/ directory not found: {SPEC_DIR}")
        sys.exit(1)

    html_files = sorted(SPEC_DIR.rglob('*.html'))
    if not html_files:
        print("No .html files found in spec/.")
        sys.exit(0)

    touched = []
    for f in html_files:
        if _process_file(f, mode, old_slug, new_slug):
            touched.append(f.relative_to(SPEC_DIR.parent))

    if touched:
        verb = f"→ '{new_slug}'" if mode == 'rep' else "removed"
        print(f"'{old_slug}' {verb} in {len(touched)} file(s):")
        for p in touched:
            print(f"  {p}")
    else:
        print(f"No internal links to '{old_slug}' found in spec/.")


if __name__ == '__main__':
    main()
