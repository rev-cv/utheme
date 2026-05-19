# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "python-dotenv",
#     "requests",
#     "Pillow",
#     "bs4",
#     "transliterate",
#     "jinja2",
#     "markdown-it-py",
# ]
# ///
import json
import os
import platform
import subprocess
import sys
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(interpolate=True)

from core.translations import LOCALE_MAP, resolve_locale

ROOT_DIR    = Path(__file__).parent
SPEC_DIR    = ROOT_DIR / "spec"
STAGING_DIR = ROOT_DIR / "staging"
WP_CONF_DIR = ROOT_DIR / "wp-conf"
MANIFEST    = ROOT_DIR / "manifest.json"


# ─── Entry point ─────────────────────────────────────────────────────────────

def run():
    _header("PIPELINE START")
    _check_lang()
    _check_admin_user()

    STAGING_DIR.mkdir(exist_ok=True)
    (STAGING_DIR / "images").mkdir(exist_ok=True)
    (STAGING_DIR / "pages").mkdir(exist_ok=True)

    # ── 1. НОРМАЛИЗАЦИЯ ИМЁН ─────────────────────────────────────────────────
    _phase(1, 12, "Нормализация имён файлов и папок")
    from core import normalize
    normalize.normalize_branding_assets(SPEC_DIR)
    normalize.bulk_rename_folders(SPEC_DIR)
    normalize.bulk_rename(SPEC_DIR)
    normalize.normalize_all_html_in_directory(SPEC_DIR)

    # ── 2. ОПРЕДЕЛЕНИЕ СТРУКТУРЫ ПРОЕКТА ────────────────────────────────────
    _phase(2, 12, "Определение типа структуры")
    from core.structure_detect import detect_structure
    structure = detect_structure(SPEC_DIR)
    print(f"  Тип:     {structure['structure_type']}")
    print(f"  Страниц: {len(structure['pages'])}")

    # ── 3. ПРОВЕРКА ЦЕЛОСТНОСТИ ──────────────────────────────────────────────
    _phase(3, 12, "Проверка целостности проекта")
    from core import structure_validate
    errors = structure_validate.check_structure_flexible(ROOT_DIR, structure["required_items"])
    errors += structure.get("slug_errors", [])
    if errors:
        print("Выполнение остановлено.")
        sys.exit(1)

    # ── 4. ТРАНСФОРМАЦИЯ ИЗОБРАЖЕНИЙ ─────────────────────────────────────────
    _phase(4, 12, "Сжатие изображений → staging/images/")
    from core import find_images
    from core import branding
    from core import compress_images as cimages
    pics = find_images.get_all_images(SPEC_DIR)
    branding.copy_branding_to_build(SPEC_DIR, STAGING_DIR / "images")
    cimages.compress_images(pics, STAGING_DIR / "images", max_kb=120)

    # ── 5. КОНВЕРТАЦИЯ HTML → WP-БЛОКИ ───────────────────────────────────────
    _phase(5, 12, "Конвертация HTML → WP-блоки → staging/pages/")
    from core import wp_html as conv
    conv.convert_pages(structure["pages"], SPEC_DIR, STAGING_DIR / "pages")

    # ── 6. ПРОВЕРКА ВНЕШНИХ ССЫЛОК ───────────────────────────────────────────
    _phase(6, 12, "Проверка внешних ссылок")
    from core import links
    links.check_links_in_articles(structure["pages"])

    # ── 7. СБОРКА МАНИФЕСТА ──────────────────────────────────────────────────
    _phase(7, 12, "Сборка и валидация manifest.json")
    from core import manifest as mf
    manifest_data = mf.build_manifest(structure, pics, SPEC_DIR)
    MANIFEST.write_text(json.dumps(manifest_data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"  Записан: {MANIFEST.relative_to(ROOT_DIR).as_posix()}")
    mf.validate_manifest(manifest_data, STAGING_DIR)

    # ── 8. ПРОВЕРКА ВНУТРЕННИХ ССЫЛОК ────────────────────────────────────────
    _phase(8, 12, "Проверка внутренних ссылок")
    links.check_internal_links(manifest_data, STAGING_DIR)

    # ── 9. ГЕНЕРАЦИЯ BASH-СКРИПТА ───────────────────────────────────────────
    _phase(9, 12, "Генерация wp-conf/provision.sh")
    WP_CONF_DIR.mkdir(exist_ok=True)
    from core.generate_sh import generate_sh
    generate_sh(manifest_data, WP_CONF_DIR / "provision.sh")

    # ── 10. ДЕПЛОЙ ───────────────────────────────────────────────────────────
    _phase(10, 12, "Деплой: Docker → WP install → медиа → контент")
    from core import docker_setup
    credentials = docker_setup.run(manifest_data, STAGING_DIR, WP_CONF_DIR)

    # ── 11. УСТАНОВКА ПЛАГИНОВ ───────────────────────────────────────────────
    _phase(11, 12, "Установка плагинов")
    if platform.system() == "Windows":
        docker_setup.activate_plugin("u-theme-styles")
        if (ROOT_DIR / "plugins" / "GEO").exists():
            docker_setup.configure_geo_plugin()
        wp_plugins_raw = os.getenv("WP_PLUGINS", "")
        for entry in [e.strip() for e in wp_plugins_raw.split(",") if e.strip()]:
            slug, _, flag = entry.partition(":")
            slug = slug.strip()
            activate = flag.strip().lower() != "no"
            docker_setup.install_plugin(slug)
            if activate:
                docker_setup.activate_plugin(slug)

    # ── 12. ФИНАЛ ────────────────────────────────────────────────────────────
    _phase(12, 12, "Финал")
    if platform.system() == "Windows":
        subprocess.run(["docker", "compose", "up", "-d", "sass"], check=True)

    if credentials:
        _print_credentials(credentials)

    _header("ГОТОВО")


# ─── Проверка конфигурации ───────────────────────────────────────────────────

def _check_admin_user():
    import re
    raw = os.environ.get("ADMIN_USER", "").strip()
    if not raw:
        raise RuntimeError("переменная ADMIN_USER не задана в .env.")
    if not re.fullmatch(r"[a-zA-Z0-9_.\-@]+", raw):
        invalid = "".join(sorted({c for c in raw if not re.match(r"[a-zA-Z0-9_.\-@]", c)}))
        raise RuntimeError(
            f"ADMIN_USER={raw!r} содержит недопустимые символы: {invalid!r}\n"
            "  WordPress разрешает только: a-z A-Z 0-9 _ . - @"
        )


def _check_lang():
    raw = os.environ.get("SITE_LANG", "").strip()
    if not raw:
        short_codes = sorted(k for k in LOCALE_MAP if len(k) <= 2)
        raise RuntimeError(
            f"переменная SITE_LANG не задана в .env.\n"
            f"  2-буквенные коды: {', '.join(short_codes)}\n"
            f"  Или укажите полный WP locale (например: en_GB, fr_BE, pt_BR)."
        )
    try:
        wp_locale, _ = resolve_locale(raw)
        print(f"  Язык: {raw} → {wp_locale}")
    except ValueError as e:
        raise RuntimeError(str(e))


# ─── Утилиты вывода ──────────────────────────────────────────────────────────

def _print_credentials(creds: dict):
    url  = creds.get("site_url",    os.environ.get("SITE_URL", "—"))
    user = creds.get("admin_user",  "—")
    pwd  = creds.get("admin_pass",  "—")
    mail = creds.get("admin_email", "—")
    app  = creds.get("app_pass",    "—")
    print(f"\n{'=' * 60}")
    print("  CREDENTIALS")
    print(f"  URL:      {url}")
    print(f"  Login:    {user}")
    print(f"  Password: {pwd}")
    print(f"  Email:    {mail}")
    print(f"  App Pass: {app}")
    print(f"{'=' * 60}")


def _header(label: str):
    print(f"\n{'=' * 60}")
    print(f"  {label}")
    print(f"{'=' * 60}")


def _phase(n: int, total: int, label: str):
    print(f"\n{'─' * 60}")
    print(f"  [{n}/{total}] {label}")
    print(f"{'─' * 60}")


if __name__ == "__main__":
    try:
        run()
    except RuntimeError as e:
        print(f"\n  ОШИБКА: {e}")
        print("Выполнение остановлено.")
        sys.exit(1)
