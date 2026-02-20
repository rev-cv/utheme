import os, sys
from pathlib import Path
from bs4 import BeautifulSoup
from collections import defaultdict

def get_detailed_image_data(root_path: Path) -> list:
    results = []
    
    # рекурсивный поиск HTML файлов
    for html_file in root_path.rglob("*.html"):
        try:
            with open(html_file, 'r', encoding='utf-8') as f:
                soup = BeautifulSoup(f, 'html.parser')
                
                for img in soup.find_all('img'):
                    src = img.get('src')
                    if not src:
                        continue

                    # базовые данные
                    image_path = Path(src)
                    
                    # извлечение SEO данных из атрибутов
                    alt_text = img.get('alt', '').strip()
                    title_attr = img.get('title', '').strip()
                    
                    # поиск Caption (WP style: <figure><img /><figcaption>Text</figcaption></figure>)
                    caption = ""
                    parent_figure = img.find_parent('figure')
                    if parent_figure:
                        figcaption = parent_figure.find('figcaption')
                        if figcaption:
                            caption = figcaption.get_text(strip=True)

                    # сбор дополнительных атрибутов (lazy loading, longdesc и т.д.)
                    description = img.get('longdesc', '').strip()
                    
                    results.append({
                        "name": image_path.stem,
                        "html": html_file,
                        "seo": {
                            "alt": alt_text,
                            "title": title_attr,
                            "description": description,
                            "caption": caption
                        },
                        "filename_full": image_path.name
                    })
                    
        except Exception as e:
            print(f"Ошибка при обработке {html_file}: {e}")
            
    return results


def find_and_select_images(folder: Path, pics: list) -> list:
    print('\nВыявление картинок и проверка их наличия на диске.')
    
    # Индекс для всех картинок в проекте для быстрого поиска
    project_images_index = defaultdict(list)
    valid_extensions = {'.avif', '.webp', '.jpg', '.jpeg', '.png', '.gif', '.svg'}
    
    # индекс всех картинок в корневой папке проекта (например, 'spec').
    # это позволяет находить картинки в любой вложенной папке, включая 'spec/IMAGES'.
    if folder and folder.exists():
        print(f"Индексирую все картинки в: {folder}")
        for file in folder.rglob("*"):
            if file.suffix.lower() in valid_extensions:
                project_images_index[file.stem].append(file)
 
    # словарь для сбора ненайденных картинок: { html_path: [img_name1, img_name2] }
    missing_report = defaultdict(set)

    for item in pics:
        target_name = item['name']
        html_path = Path(item['html'])
        html_dir = html_path.parent
        
        # Ищем все возможные файлы для этой картинки в проиндексированных
        found_paths = project_images_index.get(target_name, [])

        item["found_images"] = found_paths
        item["selected_image"] = None

        if found_paths:
            # система оценки приоритетов для выбора лучшего файла из найденных
            def selection_score(path: Path):
                score = 0
                ext = path.suffix.lower()
                # приоритет формата
                if ext == '.avif': score += 100
                elif ext == '.webp': score += 50

                # бонус, если картинка лежит в глобальной папке IMAGES
                if "IMAGES" in path.parts: score += 20

                # если картинка лежит рядом с HTML файлом
                if path.parent == html_dir: score += 1

                return score

            item["selected_image"] = max(found_paths, key=selection_score)
        else:
            # если ничего не найдено в индексе — запись в отчет об ошибках
            missing_report[html_path].add(target_name)

    # блок проверки целостности
    if missing_report:
        print("\n" + "!" * 60)
        print("КРИТИЧЕСКАЯ ОШИБКА: Некоторые картинки не найдены на диске!")
        print("!" * 60)
        
        for html_file, missing_imgs in missing_report.items():
            # вывод пути к HTML относительно текущей рабочей директории для краткости
            try:
                rel_html = html_file.relative_to(Path.cwd())
            except ValueError:
                rel_html = html_file
                
            print(f"\nФайл: {rel_html}")
            for img in sorted(missing_imgs):
                print(f"  - {img} (не найдено ни одного расширения)")
        
        print("\n" + "!" * 60)
        print("Скрипт принудительно остановлен. Проверьте наличие ресурсов.")
        print("!" * 60 + "\n")
        sys.exit(1)

    print("Все картинки из HTML успешно найдены на диске.")
    print('\n' + '='*50)
    return pics


def get_all_images(folder: Path) -> list:
    pics = get_detailed_image_data(folder)
    pics = find_and_select_images(folder, pics)
    return pics