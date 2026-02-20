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
from core import enrich_with_schedule as schedule
from core import assign_category_to_articles as cat

SPEC_DIR = Path(__file__).parent / "spec"

U30_DICT = [{"resource": f"PAGE30/CL{i}"} for i in range(1, 31)]

required_folders = [SPEC_DIR / f"PAGE30/CL{i}" for i in range(1, 31)]


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
    pages = extraction.resolve_resource_paths(SPEC_DIR, U30_DICT)

    # ИЗВЛЕЧЕНИЕ TITLE И DESCRIPTION
    pages = extraction.fetch_meta_data(pages)

    # КОНВЕРТАЦИЯ HTML в WP-BLOCKS
    pages = conv.conversion_init(pages)

    # ПРИСВОЕНИЕ ДАТЫ ПУБЛИКАЦИИ НА ОСНОВАНИИ АЛГОРИТМА
    pages = schedule.enrich_with_schedule(pages, "3d 2-3p (10-21)")

    # ДОБАВЛЕНИЕ СТАТЬЯМ КАТЕГОРИИ
    pages = cat.assign_category_to_articles("page+30", pages)

    # ЛИНКОВА КАРТИНОК СО СТАТЬЯМИ
    pages = linking.link_images_to_articles(pages, pics)

    # ЗАГРУЗКА КАРТИНОК В WP MEDIA
    pages = wp_api.upload_article_images(pages, 120)

    # ПУБЛИКАЦИЯ СТАТЕЙ НА WP
    pages = wp_api.publish_wp_pages(pages)
    