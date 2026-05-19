import hashlib
import os
from collections import Counter, defaultdict
from pathlib import Path

from bs4 import BeautifulSoup

_PROTECTED_NAMES = {"logo", "favicon", "icon"}


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

    return results


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
        html_dir    = html_path.parent

        found_paths = project_images_index.get(target_name, [])

        item["found_images"]   = found_paths
        item["selected_image"] = None

        if found_paths:
            def selection_score(path: Path):
                score = 0
                ext = path.suffix.lower()
                if ext == '.avif':  score += 100
                elif ext == '.webp': score += 50
                if "IMAGES" in path.parts: score += 20
                if path.parent == html_dir: score += 1
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
