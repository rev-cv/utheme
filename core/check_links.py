import sys
import requests
from pathlib import Path
from typing import List, Dict
from bs4 import BeautifulSoup

def _shorten_path(full_path: Path) -> str:
    """
    Сокращает абсолютный путь до относительного, начиная с папки-родителя 'spec'.
    Пример: 'E:/.../site.com/spec/page/index.html' -> 'site.com/spec/page/index.html'
    """
    try:
        parts = full_path.parts
        spec_index = parts.index('spec')
        # Начинаем с родителя 'spec', если он есть
        start_index = spec_index - 1 if spec_index > 0 else spec_index
        short_path = Path(*parts[start_index:])
        return short_path.as_posix()
    except (ValueError, IndexError):
        # Если 'spec' не найден, возвращаем последние несколько частей пути для контекста
        if len(full_path.parts) > 3:
            return Path(*full_path.parts[-3:]).as_posix()
        return full_path.as_posix()

def check_links_in_articles(articles_list: List[Dict]):
    """
    Проверяет все внешние ссылки в wp_block каждой статьи.
    В случае нахождения битых ссылок, выводит их список и предлагает прервать выполнение.

    :param articles_list: Список словарей статей с полем 'wp_block' и 'resource'.
    """
    broken_links_map = {}
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }

    total_articles = len(articles_list)
    print(f"\nПроверка ссылок в {total_articles} статьях...")

    for i, article in enumerate(articles_list):
        wp_block = article.get('wp_block')
        resource_path = article.get('resource')

        if not wp_block or not resource_path:
            continue
        
        print(f"\rПроверено: {i + 1}/{total_articles}", end="")

        soup = BeautifulSoup(wp_block, 'html.parser')
        # Используем set для удаления дубликатов ссылок в одной статье
        links_to_check = {a['href'] for a in soup.find_all('a', href=True) if a['href'].startswith(('http://', 'https://'))}

        for link in links_to_check:
            try:
                response = requests.get(link, timeout=10, allow_redirects=True, headers=headers)
                # Проверяем на коды ошибок клиента (4xx) и сервера (5xx)
                if response.status_code >= 400:
                    short_path_str = _shorten_path(resource_path)
                    broken_links_map.setdefault(short_path_str, set()).add(f"[{response.status_code}] {link}")
            except requests.exceptions.RequestException:
                # Любая ошибка при запросе (timeout, connection error) считается битой ссылкой
                short_path_str = _shorten_path(resource_path)
                broken_links_map.setdefault(short_path_str, set()).add(f"[Connection Error] {link}")

    print("\r" + " " * 30 + "\r", end="") # Очистка строки прогресса

    if not broken_links_map:
        print("✅ Битых ссылок не найдено.")
        return

    print("\n- Найдены битые ссылки:")
    for path_str, links in sorted(broken_links_map.items()):
        print(f"    - {path_str}")
        for link in sorted(list(links)):
            print(f"        {link}")

    while True:
        choice = input("\nИгнорировать? ( y / n ) ").lower().strip()
        if choice == 'y':
            print('\n' + '='*50)
            return
        elif choice == 'n':
            print("Выполнение скрипта прервано пользователем.")
            sys.exit(1)