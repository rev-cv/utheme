import os
import sys
import requests
from requests.auth import HTTPBasicAuth
import mimetypes
import json
import base64
import ssl
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError
from urllib.parse import urlparse
from pathlib import Path
import shutil
import urllib3
from core import convertation_images as cimg
from bs4 import BeautifulSoup
from core import generate_slug as gslug

# Отключаем предупреждения SSL (для локальных сайтов)
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# --- КОНФИГУРАЦИЯ ---
WP_URL = os.getenv("SITE_URL")
WP_USER = os.getenv("ADMIN_USER")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")

AUTH = HTTPBasicAuth(WP_USER, WP_APP_PASSWORD) if all([WP_USER, WP_APP_PASSWORD]) else None
API_URL = f"{WP_URL}/wp-json/wp/v2" if WP_URL else None
ARTICLES_PAGE_ID = None


def find_articles_parent_page():
    """Ищет родительскую страницу 'Articles' и сохраняет ее ID."""
    global ARTICLES_PAGE_ID
    if not API_URL or not AUTH:
        print("    Ошибка: API_URL или AUTH не настроены. Поиск родительской страницы пропущен.")
        return

    print("\nПоиск родительской страницы 'Articles' или 'guides'...")
    slugs_to_try = ['articles', 'guides']
    for slug in slugs_to_try:
        try:
            res = requests.get(f"{API_URL}/pages", auth=AUTH, params={'slug': slug}, verify=False)
            if res.status_code == 200 and res.json():
                parent_page = res.json()[0]
                ARTICLES_PAGE_ID = parent_page['id']
                print(f"    Найдена родительская страница: '{parent_page['title']['rendered']}' (ID: {ARTICLES_PAGE_ID})")
                print('='*50)
                return
        except Exception as e:
            print(f"    Ошибка при поиске страницы со слагом '{slug}': {e}")
    
    print("\nКРИТИЧЕСКАЯ ОШИБКА: Родительская страница со слагом 'articles' или 'guides' не найдена.")
    print("    Пожалуйста, создайте ее в WP и попробуйте снова.")
    sys.exit(1)

def check_wp_connection():
    """Проверяет подключение к WP API."""
    print("\nПроверка подключения к WordPress (WP_APP_PASSWORD)...")

    if not all([WP_URL, WP_USER, WP_APP_PASSWORD]):
        print("    Не найдены SITE_URL, ADMIN_USER или WP_APP_PASSWORD. Проверка пропущена.")
        return
    
    api_url = f"{WP_URL.rstrip('/')}/wp-json/wp/v2/users/me"
    auth_str = f"{WP_USER}:{WP_APP_PASSWORD}"
    auth_b64 = base64.b64encode(auth_str.encode("utf-8")).decode("utf-8")
    headers = {"Authorization": f"Basic {auth_b64}", "User-Agent": "Mozilla/5.0"}
    req = Request(api_url, headers=headers)
    
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    
    try:
        with urlopen(req, context=ctx, timeout=10) as response:
            if response.status == 200:
                data = json.loads(response.read().decode("utf-8"))
                print(f"Успешное подключение к WP! Пользователь: {data.get('name')}")
            else:
                print(f"Ошибка подключения. Статус: {response.status}")
    except HTTPError as e:
        print(f"Ошибка HTTP: {e.code} {e.reason}")
        if e.code == 401: print("   Вероятно, неверный пароль приложения или имя пользователя.")
    except URLError as e:
        print(f"Ошибка соединения: {e.reason}")
    except Exception as e:
        print(f"Ошибка: {e}")

    print('\n' + '='*50)

def check_media_exists(slug):
    """Проверяет существование картинки в WP по слагу."""
    if not API_URL or not AUTH: return None
    media_url = f"{API_URL}/media"
    params = {'slug': slug}
    try:
        response = requests.get(media_url, auth=AUTH, params=params, verify=False, timeout=10)
        if response.status_code == 200:
            data = response.json()
            if data and isinstance(data, list) and len(data) > 0:
                return data[0]
    except Exception as e:
        print(f"  ⚠️ Ошибка проверки наличия файла: {e}")
    return None

def upload_single_image(img_obj: dict, max_kb: int = 300) -> dict:
    """
    Обрабатывает и загружает одно изображение в WP.
    Модифицирует переданный словарь img_obj, добавляя поля 'original' (ID) и 'url'.
    """
    if not API_URL or not AUTH:
        print("Ошибка: API_URL или AUTH не настроены.")
        return img_obj

    original_path = img_obj.get('selected_image')
    slug = img_obj.get('name')

    if not original_path or not original_path.exists():
        return img_obj

    # --- Логика подготовки файла (сжатие и WebP) ---
    processed_path = None
    is_temporary = False
    
    try:
        # 1. Принудительная конвертация в WebP для популярных форматов
        if original_path.suffix.lower() in ['.jpg', '.jpeg', '.png']:
            processed_path = cimg.convert_to_webp_and_compress(original_path, max_kb)
            is_temporary = True
        else:
            # 2. Просто сжатие для остальных форматов
            processed_path = cimg.ensure_image_size(original_path, max_kb)
            is_temporary = (processed_path != original_path)

        if not processed_path:
            print(f"Не удалось сжать {original_path.name} до {max_kb}Kb. Пропуск.")
            print('Выполнение остановлено.')
            sys.exit(1)

        # --- загрузка в WordPress ---
        existing_media = check_media_exists(slug)
        if existing_media:
            requests.delete(f"{API_URL}/media/{existing_media['id']}", auth=AUTH, params={'force': True}, verify=False)

        with open(processed_path, 'rb') as img_file:
            final_filename = Path(processed_path).name
            mime_type, _ = mimetypes.guess_type(final_filename)
            
            headers = {
                'Content-Disposition': f'attachment; filename={final_filename}',
                'Content-Type': mime_type or 'image/webp'
            }
            
            seo = img_obj.get('seo', {})
            payload = {
                'title': seo.get('title') or slug,
                'alt_text': seo.get('alt', ''),
                'description': seo.get('description', ''),
                'caption': seo.get('caption', ''),
                'status': 'publish'
            }

            response = requests.post(f"{API_URL}/media", auth=AUTH, headers=headers, data=img_file, params=payload, verify=False)

            if response.status_code == 201:
                data = response.json()
                img_obj['original'] = data.get('id')
                img_obj['url'] = data.get('source_url')
                print(f"Загружено: {final_filename} ({os.path.getsize(processed_path)//1024}Kb)")
            else:
                print(f"Ошибка загрузки {final_filename}: {response.status_code}")
                print('Выполнение остановлено.')
                sys.exit(1)

    finally:
        # удаляется временный файл, если он был создан
        if is_temporary and processed_path and os.path.exists(processed_path):
            # только если это файл в temp_dir
            if 'tmp' in str(processed_path):
                try:
                    shutil.rmtree(Path(processed_path).parent)
                except: pass
    
    return img_obj

def upload_article_images(page_list: list[dict], max_kb: int = 300) -> list[dict]:
    """
    Загружает изображения в WP, предварительно сжимая их и конвертируя в WebP.
    """
    if not API_URL or not AUTH:
        print("Ошибка: API_URL или AUTH не настроены.")
        return page_list

    print(f"\nЗагрузка медиа (лимит: {max_kb}Kb)...\n")

    for article in page_list:
        images = article.get('images', [])
        if not images: continue

        for img_obj in images:
            upload_single_image(img_obj, max_kb)

    return page_list

def get_category_id(cat_name: str) -> int | None:
    """Возвращает ID категории по имени. Если категории нет — создает её."""
    if not API_URL or not AUTH: return None
    
    try:
        # Поиск существующей категории
        res = requests.get(f"{API_URL}/categories", auth=AUTH, params={'search': cat_name}, verify=False)
        if res.status_code == 200:
            for cat in res.json():
                if cat['name'].lower() == cat_name.lower():
                    return cat['id']
        
        # Если не найдено — создаем
        res = requests.post(f"{API_URL}/categories", auth=AUTH, json={'name': cat_name}, verify=False)
        if res.status_code == 201:
            print(f"    Создана новая категория: '{cat_name}'")
            return res.json()['id']
    except Exception as e:
        print(f"    Ошибка работы с категориями ({cat_name}): {e}")
    return None

def _create_wp_page(article_data: dict) -> int | None:
    """Создает новую страницу в WP и возвращает ее ID."""
    if not API_URL or not AUTH: return None
    if not ARTICLES_PAGE_ID:
        print("    КРИТИЧЕСКАЯ ОШИБКА: ID родительской страницы 'Articles' не определен. Создание страницы невозможно.")
        sys.exit(1)

    title = article_data.get('h1') or article_data.get('title') or "Новая страница"
    msg = f"    Создание новой страницы: '{title}'..."
    print(msg, end='\r', flush=True)

    payload = {
        'title': title,
        'status': 'draft', 
        'parent': ARTICLES_PAGE_ID,
        'template': 'article.php' # имя файла шаблона
    }

    # присвоение категории статье
    cat_name = article_data.get('cat')
    if cat_name:
        cat_id = get_category_id(cat_name)
        if cat_id:
            payload['categories'] = [cat_id]

    try:
        res = requests.post(f"{API_URL}/pages", auth=AUTH, json=payload, verify=False)
        if res.status_code == 201:
            new_page = res.json()
            new_id = new_page['id']
            print(f"    Страница создана: ID{new_id}".ljust(len(msg)))
            return new_id
        else:
            print(f"\n    Ошибка создания страницы: {res.status_code} - {res.text}")
            sys.exit(1)
    except Exception as e:
        print(f"\n    Критическая ошибка при создании страницы: {e}")
        sys.exit(1)
    
    return None


def publish_wp_pages(articles_list: list[dict]):
    """
    Модифицирует wp_block, публикует контент, задает заголовок из h1 
    и обновляет SEO данные.
    """
    if not API_URL or not AUTH:
        print("    Ошибка: API_URL или AUTH не настроены.")
        return

    print(f"\nЗапуск публикации страниц ({len(articles_list)} шт.)...\n")

    for article in articles_list:
        page_id = article.get('post')
        raw_wp_block = article.get('wp_block', '')
        images = article.get('images', [])
        article_h1 = article.get('h1', '')  # H1 для заголовка статьи
        
        # если в объекте статьи отсутствует ID, создается новая страница
        if not page_id:
            page_id = _create_wp_page(article)
            article['post'] = page_id

        if not page_id or not raw_wp_block:
            print(f"Пропуск: Отсутствует ID страницы или контент для {article.get('resource')}")
            continue

        # получить текущие данные страницы, чтобы проверить категории
        is_utility_page = False
        try:
            page_data_res = requests.get(f"{API_URL}/pages/{page_id}", auth=AUTH, verify=False, params={"_embed": 1})
            if page_data_res.status_code == 200:
                page_info = page_data_res.json()
                print(f"Запись статьи ID{page_id}: {page_info.get('title', {}).get('rendered')}")
                # проверка наличия категории "Utility Pages"
                # в WP API категории обычно находятся в ['_embedded']['wp:term']
                terms = page_info.get('_embedded', {}).get('wp:term', [])
                for term_group in terms:
                    for term in term_group:
                        if term.get('name') == "Utility Pages":
                            is_utility_page = True
                            break
        except Exception as e:
            print(f"    Не удалось проверить категории для страницы {page_id}: {e}")

        # вставка URL картинок в контент
        soup = BeautifulSoup(raw_wp_block, 'html.parser')
        figures = soup.find_all('figure')
        
        featured_media_id = None
        
        for fig in figures:
            fig_id = fig.get('id')
            if not fig_id:
                continue
            
            wp_image_data = next((img for img in images if img.get('name') == fig_id), None)
            
            if wp_image_data and wp_image_data.get('url'):
                img_tag = fig.find('img')
                if img_tag:
                    img_tag['src'] = wp_image_data['url']
                    del fig['id']
                    
                    if featured_media_id is None:
                        featured_media_id = wp_image_data.get('original')

        updated_content = str(soup)

        # подготовка payload
        update_payload = {
            'title': article_h1,
            'content': updated_content,
            'status': 'publish'
        }

        # отложенная публикация ?
        if article.get('publish_at'):
            update_payload['date'] = article['publish_at'].isoformat()
            update_payload['status'] = 'future'

        # если это НЕ Utility Page - обновление slug на основе H1
        if not is_utility_page and article_h1:
            new_slug = gslug.generate_slug(article_h1)
            update_payload['slug'] = new_slug
            # print(f"    Сгенерирован slug: {new_slug}")

        if featured_media_id:
            update_payload['featured_media'] = featured_media_id

        # отправка обновлений
        try:
            res = requests.post(f"{API_URL}/pages/{page_id}", auth=AUTH, json=update_payload, verify=False)
            if res.status_code == 200:
                # print(f"    Страница обновлена.")
                pass
            else:
                print(f"    Ошибка {page_id}: {res.status_code}")
        except Exception as e:
            print(f"    Ошибка соединения {page_id}: {e}")

        # обновление SEO (Title и Description)
        seo_title = article.get('title', '')
        seo_desc = article.get('description', '')
        update_page_seo(page_id, seo_title, seo_desc)

        print("    ok")

def update_page_seo(page_id, title, description):
    """Обновляет SEO-данные страницы через кастомные мета-поля."""
    if not API_URL or not AUTH: return
    
    url = f"{API_URL}/pages/{page_id}"
    
    # Формируем payload согласно вашим требованиям
    payload = {
        'meta': {
            '_custom_seo_title': title, 
            '_custom_seo_desc': description
        }
    }
    
    try:
        # Примечание: В некоторых темах/плагинах title страницы тоже нужно передавать явно
        response = requests.post(url, auth=AUTH, json=payload, verify=False, timeout=15)
        if response.status_code == 200:
            # print("    SEO данные записаны.")
            pass
        else:
            print(f"    Ошибка API SEO {response.status_code}: {response.text[:200]}")
    except Exception as e:
        print(f"    Ошибка соединения при обновлении SEO: {e}")

def set_site_identity(media_id):
    """Устанавливает изображение как Site Icon и Logo (если поддерживается)."""
    if not API_URL or not AUTH: return

    settings_url = f"{API_URL}/settings"
    
    try:
        # 1. Установка site_icon (стандарт WP)
        resp_icon = requests.post(settings_url, auth=AUTH, json={'site_icon': media_id}, verify=False)
        if resp_icon.status_code == 200:
            print("    Site Icon установлен.")
            
        # 2. Установка site_logo (для блочных тем)
        resp_logo = requests.post(settings_url, auth=AUTH, json={'site_logo': media_id}, verify=False)
        if resp_logo.status_code == 200:
            print("    Site Logo установлен.")
    except Exception as e:
        print(f"    Ошибка при обновлении настроек сайта: {e}")

def upload_and_set_logo(max_kb: int = 30):
    """
    Ищет spec/logo.png, загружает его в WP и устанавливает как логотип и фавикон.
    """

    print("\nЗагрузка и установка Site Icon и Site Logo...")
    # Попытка найти файл относительно корня проекта (core/../spec/logo.png)
    logo_path = Path(__file__).parent.parent / "spec" / "logo.png"
    
    if not logo_path.exists():
        print(f"    ОШИБКА: Логотип не найден по пути: {logo_path}")
        return

    img_obj = {
        'name': 'site-logo',
        'selected_image': logo_path,
        'seo': {
            'alt': 'Site Logo',
            'title': 'Site Logo',
        }
    }

    result = upload_single_image(img_obj, max_kb=max_kb)
    if result.get('original'):
        set_site_identity(result['original'])

def check_local():
    if WP_URL:
        parsed = urlparse(WP_URL)
        if parsed.hostname not in ('localhost', '127.0.0.1', '0.0.0.0'):
            print(f"\nОШИБКА: Выполнение скрипта запрещено для внешних ресурсов ({WP_URL}).")
            print("   Скрипт предназначен только для локального использования.")
            sys.exit(1)
    else:
        print('\nОШИБКА: В .env не задан SITE_URL')
        sys.exit(1)
