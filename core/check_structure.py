import json
import secrets
import sys
import re
from collections import defaultdict
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(interpolate=True)

# Правила переименования.
# Формат: "Финальное_Имя.расширение": ["вариант1.ext", "вариант2.ext", ...]
# Поиск происходит без учета регистра (case-insensitive).
RENAME_RULES = {
    "legal-notice.html": [
        "informacion-legal.html",
        "Rechtliche-Informationen.html",
        "legal-information.html",
        "impressum.html",
        "legal.html",
        "mentions-legales.html",
        "informazioni-legali.html",
        "note-legali.html",
        "juridische-informatie.html",
    ],
    "privacy-policy.html": [
        "politica-de-privacidad.html", 
        "politica-privacidad.html", 
        "Datenschutzrichtlinie.html",
        "datenschutzerklaerung.html",
        "politique-de-confidentialite.html",
        "privacy.html",
        "privacybeleid.html",
    ],
    "about-us.html": [
        "sobre-nosotros.html",
        "Über-uns.html",
        "ueber-uns.html",
        'Uber-uns.html',
        "a-propos-de-nous.html",
        "chi-siamo.html",
        "over-ons.html",
        "about.html",
    ],
    "cookie-policy.html": [
        "politica-de-cookies.html", 
        "politica-cookies.html",
        "Cookie-Richtlinie.html",
        "politique-de-cookies.html",
        "cookie.html",
        "cookiebeleid.html",
        "cookies-policy.html",
        "cookies.html",
    ],
    "ADD PAGES": [
        "PAGES SUPPLÉMENTAIRES",
        "ΒΟΗΘΗΤΙΚΕΣ ΣΕΛΙΔΕΣ",
    ]
}

import json
import sys
from pathlib import Path

def check_structure_flexible(root_directory, required_items):
    """
    Универсальная проверка структуры.
    :param root_directory: Корень проекта (Path или str)
    :param required_items: Список (строки или Path объекты)
    """
    print(f"\nПроверка структуры проекта в: {root_directory}")
    
    root_path = Path(root_directory)
    missing_items = []

    for item in required_items:
        # Преобразуем всё в строку для удобства работы с шаблонами и логами
        item_str = str(item)
        
        # 1. Обработка шаблонов со звездочкой (напр. CL*)
        if '*' in item_str:
            matches = list(root_path.glob(item_str))
            if not matches:
                missing_items.append(f"Элемент по шаблону: {item_str}")
            continue

        # 2. Обычная проверка существования элемента
        # Даже если item уже Path, оператор / в pathlib это обработает корректно
        target_path = root_path / item
        
        if not target_path.exists():
            try:
                display_path = target_path.relative_to(Path.cwd())
            except ValueError:
                display_path = target_path
            missing_items.append(f"Отсутствует: {display_path}")
            continue

    # --- Итог ---
    if missing_items:
        print("    ОШИБКА: Структура проекта не соответствует требованиям!")
        for error in missing_items:
            print(f"        {error}")
        print("Выполнение остановлено.")
        sys.exit(1)
    
    print("Общая структура проекта подтверждена.")
    print('\n' + '='*50)

# Пример вызова со смешанными типами:
# items_to_check = [
#     "ADD PAGES", 
#     Path("spec/PILLAR"), 
#     "CL*", 
#     Path("created_pages.json")
# ]
# check_structure_flexible(Path("./project"), items_to_check)




def bulk_rename(directory):
    """
    Выполняет массовое переименование файлов в указанной директории и её подпапках.
    Использует словарь RENAME_RULES для сопоставления текущих имен с новыми.
    Пропускает файлы, если целевое имя уже занято другим файлом.
    """
    root_path = Path(directory)

    print(f"\nЗапуск массового переименования ФАЙЛОВ:")
    
    if not root_path.exists():
        print(f"Папка не найдена: {root_path}")
        return

    if not RENAME_RULES:
        print("Внимание: Массив правил RENAME_RULES пуст.")
        print("    Откройте файл скрипта и добавьте названия файлов в секцию НАСТРОЙКИ.")
        return

    # Создаем карту поиска: { "имя_в_нижнем_регистре": "ФинальноеИмя" }
    lookup_map = {}
    for final_name, variants in RENAME_RULES.items():
        for variant in variants:
            lookup_map[variant.lower()] = final_name

    renamed_count = 0
    
    # Используем rglob('*') для рекурсивного прохода по всем файлам
    for file_path in root_path.rglob('*'):
        if not file_path.is_file():
            continue
            
        current_name = file_path.name
        current_name_lower = current_name.lower()
        
        if current_name_lower in lookup_map:
            target_name = lookup_map[current_name_lower]
            
            # Пропускаем, если имя уже совпадает (с учетом регистра файловой системы)
            if current_name == target_name:
                continue
                
            target_path = file_path.with_name(target_name)
            
            # Проверка на существование целевого файла (чтобы не перезаписать случайно другой файл)
            if target_path.exists() and target_path.resolve() != file_path.resolve():
                # Если это тот же файл, но отличается регистр (на Windows), pathlib обычно справляется
                if target_path.name.lower() != file_path.name.lower():
                    print(f"Пропуск: {current_name} -> {target_name}. Файл {target_name} уже существует в папке.")
                    continue

            try:
                file_path.rename(target_path)
                print(f"{file_path.parent.name}/{current_name} -> {target_name}")
                renamed_count += 1
            except OSError as e:
                print(f"Ошибка при переименовании {file_path}: {e}")

    print(f"Готово. Переименовано файлов: {renamed_count}")
    print('\n' + '='*50)

def bulk_rename_folders(directory):
    """
    Выполняет массовое переименование папок согласно правилам RENAME_RULES.
    Обход выполняется от самых глубоких папок к корневым (reverse=True),
    чтобы изменение имен родительских папок не ломало пути к вложенным.
    """
    root_path = Path(directory)
    
    if not root_path.exists():
        return
    
    if not RENAME_RULES:
        return

    # Создаем карту поиска
    lookup_map = {}
    for final_name, variants in RENAME_RULES.items():
        for variant in variants:
            lookup_map[variant.lower()] = final_name

    renamed_count = 0
    
    # Собираем все папки и сортируем их по глубине (обратный порядок),
    # чтобы сначала переименовывать вложенные папки, а потом родительские.
    dirs_to_process = sorted(
        [p for p in root_path.rglob('*') if p.is_dir()],
        key=lambda p: len(p.parts),
        reverse=True
    )

    print(f"\nЗапуск массового переименования ПАПОК:")
    
    for folder_path in dirs_to_process:
        current_name = folder_path.name
        current_name_lower = current_name.lower()
        
        if current_name_lower in lookup_map:
            target_name = lookup_map[current_name_lower]
            
            if current_name == target_name:
                continue
                
            target_path = folder_path.with_name(target_name)
            
            try:
                folder_path.rename(target_path)
                print(f"{folder_path.parent.name}/{current_name} -> {target_name}")
                renamed_count += 1
            except OSError as e:
                print(f"Ошибка при переименовании папки {folder_path}: {e}")

    print(f"Готово. Переименовано папок: {renamed_count}")
    print('\n' + '='*50)



def clean_html_spacing(file_path: Path):
    """Удаляет пробелы перед двоеточием в файле. Возвращает True, если были внесены изменения."""
    try:
        content = file_path.read_text(encoding='utf-8')
        
        # Регулярка: 
        # \s+ — один или более пробельных символов (обычные, табы, неразрывные)
        # (?=:) — lookahead, проверяет, что далее идет двоеточие, не включая его в замену
        cleaned_content = re.sub(r'\s+(?=:)', '', content)
        
        if content != cleaned_content:
            file_path.write_text(cleaned_content, encoding='utf-8')
            return True
    except Exception as e:
        print(f"    Ошибка при обработке {file_path}: {e}")
    return False

def normalize_all_html_in_directory(directory: Path):
    """
    Рекурсивно ищет все .html файлы и применяет к ним `clean_html_spacing`.
    """
    root_path = Path(directory)
    print(f"\nЗапуск нормализации HTML файлов в: {root_path}")

    if not root_path.exists():
        print(f"    Папка не найдена: {root_path}")
        return

    cleaned_count = 0
    for file_path in root_path.rglob('*.html'):
        if clean_html_spacing(file_path):
            cleaned_count += 1

    if cleaned_count > 0:
        print(f"Готово. Нормализовано файлов: {cleaned_count}")
        print('\n' + '='*50)