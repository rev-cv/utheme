import sys
from pathlib import Path
from bs4 import BeautifulSoup

def fetch_meta_data(pages_list: list[dict]) -> list[dict]:
    """
    Принимается список объектов с полем 'resource' (Path к .html файлу) 
    Извлекает данные и дополняет объекты полями h1, title и description.
    """
    enriched_list = []
    
    for item in pages_list:
        new_item = item.copy()
        file_path = item.get("resource")
        
        meta = {"title": "", "description": "", "h1": ""}
        
        if isinstance(file_path, Path) and file_path.exists():
            try:
                content = file_path.read_text(encoding='utf-8')
                soup = BeautifulSoup(content, 'html.parser')
                
                # Поиск Title
                title_tag = soup.find('title')
                if title_tag:
                    meta["title"] = title_tag.get_text(strip=True)
                else:
                    print("ВАЖНО! Title не обнаружен!")
                
                # Поиск H1
                h1_tag = soup.find('h1')
                if h1_tag:
                    meta["h1"] = h1_tag.get_text(strip=True)
                else:
                    print("ВАЖНО! H1 не обнаружен!")
                
                # Поиск Description
                desc_tag = (soup.find('meta', attrs={'name': 'description'}) or 
                           soup.find('meta', attrs={'property': 'og:description'}))
                if desc_tag:
                    meta["description"] = desc_tag.get('content', '').strip()
                else:
                    print("ВАЖНО! description не найден!")
                    
            except Exception as e:
                print(f"Ошибка чтения {file_path}: {e}")
                sys.exit(1)
        
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
        
        # если это директория — ищем первый попавшийся .html
        if resource_path.is_dir():
            html_files = list(resource_path.glob("*.html"))
            target_file = html_files[0] if html_files else (resource_path / "index.html")
        # если файл уже с расширением .html
        elif resource_path.suffix == ".html":
            target_file = resource_path
        # если путь без расширения — добавляем .html
        else:
            target_file = resource_path.with_suffix(".html")
            
        if target_file and target_file.exists():
            new_item["resource"] = target_file
        else:
            print(f"Файл не найден для '{raw_resource}'")
            print(f"    ожидался: {target_file})")
            sys.exit(1)
            
        resolved_list.append(new_item)
        
    return resolved_list


def get_meta(base_path: Path, pages_list: list[dict]) -> list[dict]:
    return enrich_pages_with_meta(base_path, pages_list)