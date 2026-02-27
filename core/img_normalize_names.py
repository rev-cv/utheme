import hashlib
import sys
from pathlib import Path
from bs4 import BeautifulSoup
from collections import Counter, defaultdict

def normalize_and_rename_files(pics: list) -> list:
    print("\nПроверка уникальности имен картинок в проекте.")

    plan = defaultdict(list)
    name_counts = Counter(item['name'] for item in pics)
    occurrence_tracker = {}

    for item in pics:
        original_name = item['name']
        html_path = Path(item['html'])
        selected_pic_path = item.get("selected_image")
        
        # 1. Генерация нового имени
        if name_counts[original_name] > 1:
            occurrence_tracker[original_name] = occurrence_tracker.get(original_name, 0) + 1
            unique_seed = f"{original_name}_{html_path}_{occurrence_tracker[original_name]}"
            hash_suffix = hashlib.md5(unique_seed.encode()).hexdigest()[:8]
            new_name = f"{original_name}-{hash_suffix}"
        else:
            new_name = original_name
        
        plan[html_path].append({
            "original": original_name,
            "new": new_name,
            "pic_path": selected_pic_path,
            "item_ref": item 
        })
    
    has_any_changes = False
    for html_file, changes in plan.items():
        # Фильтруем изменения: оставляем только те, где имена РАЗНЫЕ
        actual_changes = [c for c in changes if c['new'] != c['original']]
        
        if actual_changes:
            has_any_changes = True
            print(f"{html_file}")
            for c in actual_changes:
                print(f"  - old: {c['original']}")
                print(f"  - new:  {c['new']}")
            print("-" * 40)

    if not has_any_changes:
        print("Все имена уникальны, расширения в норме. Изменений не требуется.")
        print('\n' + '='*50)
        return pics

    # 3. Запрос подтверждения
    
    confirm = input("ПЕРЕИМЕНОВАТЬ ФАЙЛЫ?: y/n ").lower().strip()
    if confirm != 'y':
        print("Выполнение остановлено.")
        sys.exit(1)

    # 4. Выполнение (если подтверждено)
    for html_path, changes in plan.items():
        try:
            # сначала переименовываются физические файлы на диске
            for c in changes:
                if c['pic_path'] and c['pic_path'].exists():
                    new_file_path = c['pic_path'].parent / f"{c['new']}{c['pic_path'].suffix}"
                    if c['pic_path'] != new_file_path:
                        c['pic_path'].rename(new_file_path)
                        # Обновляем путь в исходном объекте
                        c['item_ref']["selected_image"] = new_file_path

                # Обновляем имя в исходном объекте, чтобы оно было уникальным для последующих шагов
                c['item_ref']['name'] = c['new']

            # затем обнавляяются ссылки внутри HTML
            with open(html_path, 'r', encoding='utf-8') as f:
                soup = BeautifulSoup(f, 'html.parser')
            
            changed_html = False
            for img in soup.find_all('img'):
                src = img.get('src')
                if src:
                    src_p = Path(src)
                    # ищется соответствие по оригинальному имени среди изменений этого файла
                    for c in changes:
                        if src_p.stem == c['original']:
                            # берем расширение из выбранного файла (avif/webp и т.д.)
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