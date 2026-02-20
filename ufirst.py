# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "python-dotenv",
#     "requests",
#     "Pillow",
#     "bs4",
#     "transliterate",
# ]
# ///
import sys
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(interpolate=True)

from core import convertation_to_wp as conv
from core import extract_meta_from_html as extraction
from core import img_find_images
from core import link_images_to_articles as linking
from core import wp_api
from core import check_structure

SPEC_DIR = Path(__file__).parent / "spec"

U5_DICT = [
    {
        "resource": "PILLAR",
        "post": 4
    },
    {
        "resource": "CL1",
        "post": 6
    },
    {
        "resource": "CL2",
        "post": 7
    },
    {
        "resource": "CL3",
        "post": 8
    },
    {
        "resource": "CL4",
        "post": 9
    },
    {
        "resource": "CL5",
        "post": 10
    },
    {
        "resource": "ADD PAGES/about-us",
        "post": 11
    },
    {
        "resource": "ADD PAGES/cookie-policy",
        "post": 12
    },
    {
        "resource": "ADD PAGES/privacy-policy",
        "post": 13
    },
    {
        "resource": "ADD PAGES/legal-notice",
        "post": 14
    },
]

required_folders = [
    SPEC_DIR / "PILLAR",
    SPEC_DIR / "ADD PAGES",
    SPEC_DIR / "logo.png",
    SPEC_DIR / "CL1", 
    SPEC_DIR / "CL2", 
    SPEC_DIR / "CL3", 
    SPEC_DIR / "CL4", 
    SPEC_DIR / "CL5",
]

if __name__ == "__main__":
    # ПРОВЕРКА: ДАННЫЙ СКРИПТ ПРЕДНАЗНАЧЕН ТОЛЬКО ДЛЯ ЛОКАЛЬНОГО САЙТА
    wp_api.check_local()

    # ПОИСК РОДИТЕЛЬСКОЙ СТРАНИЦЫ ДЛЯ СТАТЕЙ
    wp_api.find_articles_parent_page()

    # ПРОВЕРКА ЦЕЛОСТНОСТИ СТРУКТУРЫ ПРОЕКТА НЕОБХОДИМОГО ДЛЯ ВЫПОЛНЕНИЯ СКРИПТА
    check_structure.check_structure_flexible(SPEC_DIR.parent, required_folders)

    # ПРОВЕРКА ЦЕЛОСТНОСТИ КАРТИНОК И СОЗДАНИЕ БАЗЫ ВСЕХ КАРТИНОК ПРОЕКТА
    pics = img_find_images.get_all_images(SPEC_DIR)

    # НОРМАЛИЗАЦИЯ ПУТЕЙ ДО РЕСУРСОВ
    pages = extraction.resolve_resource_paths(SPEC_DIR, U5_DICT)

    # ИЗВЛЕЧЕНИЕ TITLE И DESCRIPTION
    pages = extraction.fetch_meta_data(pages)

    # КОНВЕРТАЦИЯ HTML в WP-BLOCKS
    pages = conv.conversion_init(pages)

    # ЛИНКОВА КАРТИНОК СО СТАТЬЯМИ
    pages = linking.link_images_to_articles(pages, pics)

    # ЗАГРУЗКА КАРТИНОК В WP MEDIA
    pages = wp_api.upload_article_images(pages, 120)

    # ПУБЛИКАЦИЯ СТАТЬИ НА WP
    pages = wp_api.publish_wp_pages(pages)
    
    # УСТАНАВКА ФАВИКОНА И ЛОГО САЙТА
    wp_api.upload_and_set_logo(30)