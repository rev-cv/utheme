
# Теги для поиска в HTML файлах.
SEARCH_TAGS = [
    "<table",
]

def scan_html_tags(directory):
    """
    Рекурсивно ищет файлы .html в указанной директории и проверяет их содержимое
    на наличие тегов, заданных в списке SEARCH_TAGS. Выводит отчет о найденных совпадениях.
    """
    root_path = Path(directory)
    
    if not SEARCH_TAGS:
        return

    print(f"Сканирование HTML файлов на наличие тегов: {SEARCH_TAGS}")
    
    found_any = False
    results = {tag: [] for tag in SEARCH_TAGS}

    for file_path in root_path.rglob('*.html'):
        try:
            content = file_path.read_text(encoding='utf-8', errors='ignore')
            for tag in SEARCH_TAGS:
                if tag in content:
                    results[tag].append(file_path.relative_to(root_path))
        except Exception as e:
            print(f"⚠️ Ошибка чтения {file_path}: {e}")

    for tag, files in results.items():
        if files:
            found_any = True
            print(f"\n🚩 Тег '{tag}' найден в {len(files)} файлах:")
            for f in files:
                print(f" - {f}")

    if not found_any:
        print("✅ Искомые теги не найдены.")