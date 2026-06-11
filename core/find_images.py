"""
Методология поиска и сопоставления картинок
============================================

Модуль работает в три последовательных шага.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ШАГ 1 — get_detailed_image_data(root_path)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Рекурсивно обходит все *.html в root_path (rglob).
Для каждого <img> извлекает:
  • stem файла из атрибута src (т.е. «hero» из «images/hero.webp»)
  • alt, title, longdesc, figcaption — SEO-поля
Дубли по stem внутри одного HTML пропускаются (seen_images).
Результат — плоский список записей вида:
  { name, html, seo: {alt, title, description, caption}, filename_full }

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ШАГ 2 — find_and_select_images(folder, pics)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Строит индекс stem → [список физических файлов] по всей папке folder
(тоже rglob). Затем для каждой записи из шага 1 выбирает лучший
физический файл через selection_score:

  +100  расширение .avif
  + 50  расширение .webp
  +  5  × (количество общих компонентов пути с HTML-файлом)
         — реализовано в _path_proximity(); позволяет корректно
           выбирать между одноимёнными hero.webp из разных
           подпапок в многосекционных проектах (MINIREVIEW_2026)
  + 10  файл лежит в папке с именем «images» (case-insensitive)

Приоритеты: формат > близость к HTML > папка images.
Если файл не найден ни в одном расширении — собирается missing_report
и после обхода бросается RuntimeError со списком отсутствующих.

ВАЖНО: индексирование идёт по stem без расширения. Значит, если
в проекте есть hero.webp и hero.jpg — они оба попадут в индекс
под ключом «hero». selection_score выберёт лучший формат (.webp > .jpg).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ШАГ 3 — _normalize_image_names(pics)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
После выбора файлов проверяет уникальность имён в рамках всего
проекта. Если один и тот же stem встречается в нескольких HTML
(что неизбежно для hero, section-1 и т.п. в многосекционных
проектах), каждому вхождению добавляется уникальный хеш-суффикс:
  hero → hero-a3f8c21b  (для страницы A)
  hero → hero-7d04e59a  (для страницы B)

Защищённые имена (logo, favicon, icon) из списка _PROTECTED_NAMES
переименованию не подлежат — они всегда уникальны по назначению.

Переименование происходит на диске (Path.rename) и синхронно в
атрибуте src соответствующего HTML (BeautifulSoup).
"""

import hashlib
import re
from collections import Counter, defaultdict
from pathlib import Path
from bs4 import BeautifulSoup

_PROTECTED_NAMES = {"logo", "favicon", "icon"}
_MD_IMG = re.compile(r'!\[([^\]]*)\]\(([^)]+)\)')


def get_detailed_image_data(root_path: Path) -> list:
    results = []

    for html_file in root_path.rglob("*.html"):
        try:
            with open(html_file, 'r', encoding='utf-8') as f:
                soup = BeautifulSoup(f, 'html.parser')

                seen_images = set()

                for img in soup.find_all('img'):
                    src = img.get('src')
                    if not src:
                        continue

                    image_path = Path(src)

                    if image_path.stem in seen_images:
                        continue
                    seen_images.add(image_path.stem)

                    alt_text   = img.get('alt',   '').strip()
                    title_attr = img.get('title', '').strip()

                    caption = ""
                    parent_figure = img.find_parent('figure')
                    if parent_figure:
                        figcaption_tag = parent_figure.find('figcaption')
                        if figcaption_tag:
                            caption = figcaption_tag.get_text(strip=True)
                        else:
                            next_sibling = parent_figure.find_next_sibling()
                            if next_sibling and (next_sibling.name == 'figcaption' or 'wp-caption-text' in next_sibling.get('class', [])):
                                caption = next_sibling.get_text(strip=True)

                    description = img.get('longdesc', '').strip()

                    results.append({
                        "name": image_path.stem,
                        "html": html_file,
                        "seo": {
                            "alt":         alt_text,
                            "title":       title_attr,
                            "description": description,
                            "caption":     caption,
                        },
                        "filename_full": image_path.name,
                    })

        except Exception as e:
            print(f"Ошибка при обработке {html_file}: {e}")

    _re_index_md = re.compile(r'^index\d*\.md$')
    _fm_re = re.compile(r'^---\n(.*?)\n---', re.DOTALL)
    for md_file in root_path.rglob("*.md"):
        if not _re_index_md.match(md_file.name):
            continue
        try:
            content = md_file.read_text(encoding='utf-8')
            seen_images: set[str] = set()

            # headimg из frontmatter идёт первым → становится images[0] (thumbnail)
            fm_m = _fm_re.match(content)
            if fm_m:
                for line in fm_m.group(1).splitlines():
                    if ':' in line:
                        k, _, v = line.partition(':')
                        if k.strip() == 'headimg':
                            src = v.strip().strip("\"'")
                            if src:
                                image_path = Path(src)
                                stem = image_path.stem
                                if stem not in seen_images:
                                    seen_images.add(stem)
                                    results.append({
                                        "name":          stem,
                                        "html":          md_file,
                                        "seo":           {"alt": "", "title": "", "description": "", "caption": ""},
                                        "filename_full": image_path.name,
                                    })

            for m in _MD_IMG.finditer(content):
                alt = m.group(1).strip()
                src = m.group(2).strip()
                image_path = Path(src)
                if image_path.stem in seen_images:
                    continue
                seen_images.add(image_path.stem)
                results.append({
                    "name":          image_path.stem,
                    "html":          md_file,
                    "seo":           {"alt": alt, "title": "", "description": "", "caption": ""},
                    "filename_full": image_path.name,
                })
        except Exception as e:
            print(f"Ошибка при обработке {md_file}: {e}")

    return results


def _path_proximity(img_path: Path, html_path: Path) -> int:
    """Number of path components shared from the root (absolute paths)."""
    common = 0
    for a, b in zip(img_path.parts, html_path.parts):
        if a == b:
            common += 1
        else:
            break
    return common


def find_and_select_images(folder: Path, pics: list) -> list:
    print('\nВыявление картинок и проверка их наличия на диске.')

    project_images_index = defaultdict(list)
    valid_extensions = {'.avif', '.webp', '.jpg', '.jpeg', '.png', '.gif', '.svg'}

    if folder and folder.exists():
        print(f"Индексирую все картинки в: {folder}")
        for file in folder.rglob("*"):
            if file.suffix.lower() in valid_extensions:
                project_images_index[file.stem].append(file)

    missing_report = defaultdict(set)

    for item in pics:
        target_name = item['name']
        html_path   = Path(item['html'])

        found_paths = project_images_index.get(target_name, [])

        item["found_images"]   = found_paths
        item["selected_image"] = None

        if found_paths:
            def selection_score(path: Path, _html=html_path):
                score = 0
                ext = path.suffix.lower()
                if ext == '.avif':   score += 100
                elif ext == '.webp': score += 50
                # Prefer images closer in the directory tree to the HTML file
                score += _path_proximity(path, _html) * 5
                # Prefer files that sit inside an 'images' sibling/parent folder
                if path.parent.name.lower() == "images": score += 10
                return score

            item["selected_image"] = max(found_paths, key=selection_score)
        else:
            missing_report[html_path].add(target_name)

    if missing_report:
        print("\n" + "!" * 60)
        print("КРИТИЧЕСКАЯ ОШИБКА: Некоторые картинки не найдены на диске!")
        print("!" * 60)

        for html_file, missing_imgs in missing_report.items():
            try:
                rel_html = html_file.relative_to(Path.cwd())
            except ValueError:
                rel_html = html_file

            print(f"\nФайл: {rel_html}")
            for img in sorted(missing_imgs):
                print(f"  - {img} (не найдено ни одного расширения)")

        raise RuntimeError("Не найдены файлы изображений — проверьте наличие ресурсов")

    print("Все картинки из HTML успешно найдены на диске.")
    print('\n' + '=' * 50)
    return pics


def _normalize_image_names(pics: list) -> list:
    """Дедуплицирует имена картинок: добавляет хеш-суффикс и обновляет src в HTML."""
    print("\nПроверка уникальности имен картинок в проекте.")

    plan        = defaultdict(list)
    name_counts = Counter(item['name'] for item in pics)
    occurrence_tracker = {}

    for item in pics:
        original_name  = item['name']
        html_path      = Path(item['html'])
        selected_pic_path = item.get("selected_image")

        if name_counts[original_name] > 1 and original_name not in _PROTECTED_NAMES:
            occurrence_tracker[original_name] = occurrence_tracker.get(original_name, 0) + 1
            unique_seed = f"{original_name}_{html_path}_{occurrence_tracker[original_name]}"
            hash_suffix = hashlib.md5(unique_seed.encode()).hexdigest()[:8]
            new_name = f"{original_name}-{hash_suffix}"
        else:
            new_name = original_name

        plan[html_path].append({
            "original": original_name,
            "new":      new_name,
            "pic_path": selected_pic_path,
            "item_ref": item,
        })

    has_any_changes = any(
        c['new'] != c['original']
        for changes in plan.values()
        for c in changes
    )

    if not has_any_changes:
        print("Все имена уникальны. Изменений не требуется.")
        print('\n' + '=' * 50)
        return pics

    for html_path, changes in plan.items():
        try:
            for c in changes:
                if c['new'] == c['original']:
                    continue
                if c['pic_path'] and c['pic_path'].exists():
                    new_file_path = c['pic_path'].parent / f"{c['new']}{c['pic_path'].suffix}"
                    if c['pic_path'] != new_file_path:
                        c['pic_path'].rename(new_file_path)
                        c['item_ref']["selected_image"] = new_file_path
                        print(f"  Переименован: {c['original']} → {c['new']}")
                c['item_ref']['name'] = c['new']

            if html_path.suffix == '.md':
                content = html_path.read_text(encoding='utf-8')
                changed_md = False

                def _replace_md(m: re.Match) -> str:
                    nonlocal changed_md
                    alt, src = m.group(1), m.group(2).strip()
                    p = Path(src)
                    for c in changes:
                        if c['new'] != c['original'] and p.stem == c['original']:
                            ext = c['pic_path'].suffix if c['pic_path'] else p.suffix
                            new_src = (p.parent / f"{c['new']}{ext}").as_posix()
                            changed_md = True
                            return f'![{alt}]({new_src})'
                    return m.group(0)

                new_content = _MD_IMG.sub(_replace_md, content)
                if changed_md:
                    html_path.write_text(new_content, encoding='utf-8')
            else:
                with open(html_path, 'r', encoding='utf-8') as f:
                    soup = BeautifulSoup(f, 'html.parser')

                changed_html = False
                for img in soup.find_all('img'):
                    src = img.get('src')
                    if src:
                        src_p = Path(src)
                        for c in changes:
                            if src_p.stem == c['original']:
                                ext = c['pic_path'].suffix if c['pic_path'] else src_p.suffix
                                img['src'] = str(src_p.parent / f"{c['new']}{ext}")
                                changed_html = True

                if changed_html:
                    with open(html_path, 'w', encoding='utf-8') as f:
                        f.write(str(soup))

        except Exception as e:
            print(f"Ошибка при обработке {html_path}: {e}")

    print("Готово! Все изменения применены.")
    return pics


def get_all_images(folder: Path) -> list:
    pics = get_detailed_image_data(folder)
    pics = find_and_select_images(folder, pics)
    pics = _normalize_image_names(pics)
    return pics
