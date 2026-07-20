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
#     "pyyaml",
#     "rich",
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
from core.console import ACTION_STYLE, HEADER_STYLE, console, header, phase, action, result, warn, error

ROOT_DIR    = Path(__file__).parent
SPEC_DIR    = ROOT_DIR / "spec"
STAGING_DIR = ROOT_DIR / "staging"
WP_CONF_DIR = ROOT_DIR / "wp-conf"
MANIFEST    = ROOT_DIR / "manifest.json"


# ─── Entry point ─────────────────────────────────────────────────────────────

def run():
    header("PIPELINE START")

    # ── 1. ПОДГОТОВКА И ПРОВЕРКА ПРОЕКТА ────────────────────────────────────
    phase(1, 9, "Подготовка и проверка проекта")
    action("Проверка конфигурации (.env)")
    _check_lang()
    _check_admin_user()
    result("Конфигурация .env проверена.", style="green")

    STAGING_DIR.mkdir(exist_ok=True)
    (STAGING_DIR / "images").mkdir(exist_ok=True)
    (STAGING_DIR / "pages").mkdir(exist_ok=True)

    from core import normalize
    normalize.normalize_branding_assets(SPEC_DIR)
    normalize.bulk_rename_folders(SPEC_DIR)
    normalize.bulk_rename(SPEC_DIR)
    normalize.normalize_all_html_in_directory(SPEC_DIR)

    from core.structure_detect import detect_structure
    structure = detect_structure(SPEC_DIR)
    result(f"Тип: {structure['structure_type']}  |  Страниц: {len(structure['pages'])}", style="green")

    from core import structure_validate
    errors = structure_validate.check_structure_flexible(ROOT_DIR, structure["required_items"])
    slug_errors = structure.get("slug_errors", [])
    if slug_errors:
        error("Проблемы со slug-именами папок!")
        for e in slug_errors:
            console.print(f"        {e}")
    errors += slug_errors
    if errors:
        console.print("Выполнение остановлено.")
        sys.exit(1)

    # ── 2. ОБФУСКАЦИЯ ТЕМЫ ───────────────────────────────────────────────────
    phase(2, 9, "Обфускация CSS-классов темы → staging/class_map.json")
    from core.theme_obfuscate import obfuscate_theme, check_keyclass_coverage
    _uncovered = check_keyclass_coverage(ROOT_DIR / "utheme")
    if _uncovered:
        warn("ut-* классы без покрытия в keyclass YAML:")
        for _cls in _uncovered:
            console.print(f"    {_cls}")
    _site_url = os.getenv("SITE_DOMAIN") or os.getenv("SITE_URL") or ""
    if not _site_url:
        warn("SITE_DOMAIN не задан — обфускация пропущена")
    else:
        obfuscate_theme(ROOT_DIR / "utheme", _site_url, STAGING_DIR / "class_map.json")

    # ── 3. ТРАНСФОРМАЦИЯ ИЗОБРАЖЕНИЙ ─────────────────────────────────────────
    phase(3, 9, "Сжатие изображений → staging/images/")
    from core import find_images
    from core import branding
    from core import compress_images as cimages
    pics = find_images.get_all_images(SPEC_DIR)
    branding.copy_branding_to_build(SPEC_DIR, STAGING_DIR / "images")
    cimages.compress_images(pics, STAGING_DIR / "images", max_kb=100)

    # ── 4. КОНВЕРТАЦИЯ HTML → WP-БЛОКИ ───────────────────────────────────────
    phase(4, 9, "Конвертация HTML → WP-блоки → staging/pages/")
    from core import wp_html as conv
    conv.convert_pages(structure["pages"], SPEC_DIR, STAGING_DIR / "pages")

    # ── 5. СБОРКА МАНИФЕСТА ──────────────────────────────────────────────────
    phase(5, 9, "Сборка и валидация manifest.json")
    from core import manifest as mf
    manifest_data = mf.build_manifest(structure, pics, SPEC_DIR)
    MANIFEST.write_text(json.dumps(manifest_data, ensure_ascii=False, indent=2), encoding="utf-8")
    action("Запись manifest.json")
    result(f"Записан: {MANIFEST.relative_to(ROOT_DIR).as_posix()}", style="green")
    mf.validate_manifest(manifest_data, STAGING_DIR)

    # ── 6. ПРОВЕРКА ССЫЛОК ───────────────────────────────────────────────────
    phase(6, 9, "Проверка внешних и внутренних ссылок")
    from core import links
    links.check_links_in_articles(structure["pages"])
    links.check_internal_links(manifest_data, STAGING_DIR)

    # ── 7. ГЕНЕРАЦИЯ BASH-СКРИПТА ───────────────────────────────────────────
    phase(7, 9, "Генерация wp-conf/provision.sh")
    WP_CONF_DIR.mkdir(exist_ok=True)
    from core.generate_sh import generate_sh
    generate_sh(manifest_data, WP_CONF_DIR / "provision.sh")

    # ── 8. ДЕПЛОЙ ────────────────────────────────────────────────────────────
    phase(8, 9, "Деплой: Docker → WP install → медиа → контент")
    from core import docker_setup
    credentials = docker_setup.run(manifest_data, STAGING_DIR, WP_CONF_DIR)

    # ── 9. ЗАВЕРШЕНИЕ НАСТРОЙКИ ──────────────────────────────────────────────
    phase(9, 9, "Завершение настройки")
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

    if platform.system() == "Windows":
        action("Запуск Sass-контейнера")
        proc = subprocess.run(
            ["docker", "compose", "up", "-d", "sass"],
            capture_output=True,
            text=True,
        )
        if proc.returncode != 0:
            raise RuntimeError(f"Не удалось запустить Sass-контейнер: {proc.stderr.strip()}")
        result("Sass-контейнер запущен.", style="green")

    if credentials:
        _print_credentials(credentials)

    header("ГОТОВО")


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
        result(f"Язык: {raw} → {wp_locale}", style="green")
    except ValueError as e:
        raise RuntimeError(str(e))


# ─── Утилиты вывода ──────────────────────────────────────────────────────────

def _print_credentials(creds: dict):
    from rich.panel import Panel
    from rich.table import Table

    url  = creds.get("site_url",    os.environ.get("SITE_URL", "—"))
    user = creds.get("admin_user",  "—")
    pwd  = creds.get("admin_pass",  "—")
    mail = creds.get("admin_email", "—")
    app  = creds.get("app_pass",    "—")

    table = Table.grid(padding=(0, 1))
    table.add_column(style=ACTION_STYLE)
    table.add_column()
    table.add_row("URL:",      url)
    table.add_row("Login:",    user)
    table.add_row("Password:", pwd)
    table.add_row("Email:",    mail)
    table.add_row("App Pass:", app)

    console.print()
    console.print(Panel(table, title="CREDENTIALS", border_style=HEADER_STYLE))


if __name__ == "__main__":
    try:
        run()
    except RuntimeError as e:
        error(str(e))
        console.print("Выполнение остановлено.")
        sys.exit(1)
