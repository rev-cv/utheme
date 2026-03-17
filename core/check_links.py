import sys
import requests
from pathlib import Path
from typing import List, Dict
from bs4 import BeautifulSoup
from concurrent.futures import ThreadPoolExecutor, as_completed

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

def check_single_link(link: str, headers: dict):
    """Вспомогательная функция для проверки одной ссылки."""
    try:
        # Используем head для ускорения (запрашиваем только заголовки)
        # Если head запрещен, можно переключиться на get
        response = requests.head(link, timeout=10, allow_redirects=True, headers=headers)
        if response.status_code >= 400:
            # Если HEAD не удался, пробуем GET (некоторые серверы блокируют HEAD)
            response = requests.get(link, timeout=10, allow_redirects=True, headers=headers)
            
        if response.status_code >= 400:
            return f"[{response.status_code}] {link}"
    except requests.exceptions.RequestException:
        return f"[Connection Error] {link}"
    return None

def check_links_in_articles(articles_list: List[Dict], max_workers: int = 10):
    """
    Проверяет внешние ссылки в несколько потоков.
    :param max_workers: Количество одновременных потоков.
    """
    broken_links_map = {}
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }

    total_articles = len(articles_list)
    print(f"\n🚀 Запуск многопоточной проверки ссылок ({max_workers} потоков)...")

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_article = {}

        for article in articles_list:
            wp_block = article.get('wp_block')
            resource_path = article.get('resource')
            if not wp_block or not resource_path:
                continue

            soup = BeautifulSoup(wp_block, 'html.parser')
            links = {a['href'] for a in soup.find_all('a', href=True) if a['href'].startswith(('http://', 'https://'))}
            
            # Создаем задачи для каждой уникальной ссылки в статье
            for link in links:
                future = executor.submit(check_single_link, link, headers)
                future_to_article[future] = resource_path

        # Обработка результатов по мере завершения
        completed_count = 0
        total_futures = len(future_to_article)

        for future in as_completed(future_to_article):
            completed_count += 1
            resource_path = future_to_article[future]
            result = future.result()

            if result:
                short_path_str = _shorten_path(resource_path)
                broken_links_map.setdefault(short_path_str, set()).add(result)
            
            print(f"\rПрогресс: {completed_count}/{total_futures} ссылок проверено", end="")

    print("\r" + " " * 50 + "\r", end="")

    # Логика вывода и прерывания (как в оригинале)
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
            return
        elif choice == 'n':
            print("Выполнение прервано.")
            sys.exit(1)