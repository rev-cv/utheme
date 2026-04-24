# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "python-dotenv",
#     "requests",
#     "Pillow",
#     "bs4",
#     "transliterate",
#     "jinja2",
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

from core.translations import LANG_MAP

ROOT_DIR     = Path(__file__).parent
SPEC_DIR     = ROOT_DIR / "spec"
STAGING_DIR  = ROOT_DIR / "staging"
WP_CONF_DIR  = ROOT_DIR / "wp-conf"
MANIFEST     = ROOT_DIR / "manifest.json"


# ─── Entry point ─────────────────────────────────────────────────────────────

def run():
    _header("PIPELINE START")
    _check_lang()

    STAGING_DIR.mkdir(exist_ok=True)
    (STAGING_DIR / "images").mkdir(exist_ok=True)
    (STAGING_DIR / "pages").mkdir(exist_ok=True)

    # ── 1. НОРМАЛИЗАЦИЯ ИМЁН ─────────────────────────────────────────────────
    _phase(1, 10, "Нормализация имён файлов и папок")
    from core import check_structure
    check_structure.normalize_branding_assets(SPEC_DIR)
    check_structure.bulk_rename_folders(SPEC_DIR)
    check_structure.bulk_rename(SPEC_DIR)
    check_structure.normalize_all_html_in_directory(SPEC_DIR)

    # ── 2. ОПРЕДЕЛЕНИЕ СТРУКТУРЫ ПРОЕКТА ────────────────────────────────────
    _phase(2, 10, "Определение типа структуры")
    from core.detect_structure import detect_structure
    structure = detect_structure(SPEC_DIR)
    print(f"  Тип:     {structure['structure_type']}")
    print(f"  Страниц: {len(structure['pages'])}")

    # ── 3. ПРОВЕРКА ЦЕЛОСТНОСТИ ──────────────────────────────────────────────
    _phase(3, 10, "Проверка целостности проекта")
    check_structure.check_structure_flexible(ROOT_DIR, structure["required_items"])

    # ── 4. ТРАНСФОРМАЦИЯ ИЗОБРАЖЕНИЙ ─────────────────────────────────────────
    _phase(4, 10, "Сжатие изображений → staging/images/")
    from core import convertation_images as cimg
    from core import img_find_images
    pics = img_find_images.get_all_images(SPEC_DIR)
    _copy_branding_to_build(STAGING_DIR / "images")
    _compress_images(pics, STAGING_DIR / "images", max_kb=120)

    # ── 5. КОНВЕРТАЦИЯ HTML → WP-БЛОКИ ───────────────────────────────────────
    _phase(5, 10, "Конвертация HTML → WP-блоки → staging/pages/")
    _convert_html_to_wp(structure["pages"], STAGING_DIR / "pages")

    # ── 6. ПРОВЕРКА ВНЕШНИХ ССЫЛОК ───────────────────────────────────────────
    _phase(6, 10, "Проверка внешних ссылок")
    from core import check_links
    check_links.check_links_in_articles(structure["pages"])

    # ── 7. СБОРКА МАНИФЕСТА ──────────────────────────────────────────────────
    _phase(7, 10, "Сборка manifest.json")
    manifest = _build_manifest(structure, pics)
    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"  Записан: {MANIFEST.relative_to(ROOT_DIR).as_posix()}")

    # ── 8. ВАЛИДАЦИЯ МАНИФЕСТА ───────────────────────────────────────────────
    _phase(8, 10, "Валидация manifest.json")
    _validate_manifest(manifest)

    # ── 9. ГЕНЕРАЦИЯ BASH-СКРИПТА ────────────────────────────────────────────
    _phase(9, 10, "Генерация wp-conf/provision.sh")
    WP_CONF_DIR.mkdir(exist_ok=True)
    from core.generate_sh import generate_sh
    generate_sh(manifest, WP_CONF_DIR / "provision.sh")

    # ── 10. ДЕПЛОЙ ───────────────────────────────────────────────────────────
    _phase(10, 10, "Деплой: Docker → WP install → медиа → контент")
    from core import docker_setup
    docker_setup.run(manifest, STAGING_DIR, WP_CONF_DIR)

    if platform.system() == "Windows":
        _activate_plugin("u-theme-styles")
        subprocess.run(["docker", "compose", "up", "-d", "sass"], check=True)
        _install_and_activate_plugin("all-in-one-wp-migration")

    _header("ГОТОВО")


# ─── Шаг 5: конвертация HTML → WP-блоки ─────────────────────────────────────

def _convert_html_to_wp(pages: list[dict], out_dir: Path):
    from core import convertation_to_wp as conv
    from core import extract_meta_from_html as extraction

    # структурные страницы (content=None) не имеют HTML-источника
    content_pages = [p for p in pages if p.get("content")]

    resource_list = [{"resource": p["content"], **p} for p in content_pages]
    resource_list = extraction.resolve_resource_paths(SPEC_DIR, resource_list)
    converted     = conv.conversion_init(resource_list)

    for page, result in zip(content_pages, converted):
        wp_content = result.get("wp_block", "")
        out_file   = out_dir / f"{page['slug']}.wp"
        out_file.write_text(wp_content, encoding="utf-8")

    print(f"  Конвертировано страниц: {len(content_pages)} (структурных пропущено: {len(pages) - len(content_pages)})")


# ─── Шаг 4: сжатие изображений ───────────────────────────────────────────────

def _compress_images(pics: list[dict], out_dir: Path, max_kb: int):
    import shutil
    from core import convertation_images as cimg

    col = 42
    print(f"\n  {'Файл':<{col}} {'До':>8}   {'После':>8}   Статус")
    print(f"  {'─'*col} {'─'*8}   {'─'*8}   {'─'*8}")

    done = skipped = errors = 0

    for pic in pics:
        src = pic.get("selected_image")
        if not src or not Path(src).exists():
            continue
        src     = Path(src)
        dst     = out_dir / src.with_suffix(".webp").name
        src_kb  = src.stat().st_size / 1024
        name    = src.name[:col]

        if dst.exists():
            dst_kb = dst.stat().st_size / 1024
            print(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   пропуск")
            pic["selected_image"] = dst
            skipped += 1
            continue

        if src_kb < max_kb and src.suffix.lower() == ".webp":
            shutil.copy2(src, dst)
            dst_kb = dst.stat().st_size / 1024
            print(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   копия")
            pic["selected_image"] = dst
            done += 1
            continue

        tmp = cimg.convert_to_webp_and_compress(src, max_kb)
        if tmp and Path(tmp).exists():
            dst_kb = Path(tmp).stat().st_size / 1024
            shutil.move(str(tmp), dst)
            pic["selected_image"] = dst
            print(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   ✓")
            done += 1
        else:
            print(f"  {name:<{col}} {src_kb:>6.1f} KB   {'—':>8}   ошибка")
            errors += 1

    print(f"\n  Сжато: {done}  |  Пропущено (уже есть): {skipped}  |  Ошибок: {errors}")


# ─── Шаг 7: сборка манифеста ─────────────────────────────────────────────────

def _build_manifest(structure: dict, pics: list[dict]) -> dict:
    from core import extract_meta_from_html as extraction
    from core import link_images_to_articles as linking
    from core import enrich_with_schedule as schedule
    from core import translations

    lang  = os.environ.get("SITE_LANG", "EN")
    pages = structure["pages"]

    # ── контентные страницы (есть HTML-источник) ─────────────────────────────
    content_pages    = [p for p in pages if p.get("content")]
    structural_pages = [p for p in pages if not p.get("content")]

    resource_list = [{"resource": p["content"], **p} for p in content_pages]
    resource_list = extraction.resolve_resource_paths(SPEC_DIR, resource_list)
    resource_list = extraction.fetch_meta_data(resource_list)
    resource_list = linking.link_images_to_articles(resource_list, pics)

    for item in resource_list:
        slug = item["slug"]
        item["content"]   = f"{slug}.wp"
        item["title"]     = item.get("title") or item.get("h1") or slug
        item["seo_title"] = item.get("headline") or item.get("title")
        item["seo_descr"] = item.get("description")
        item["images"]    = [
            Path(img["selected_image"]).name
            for img in item.get("images", [])
            if img.get("selected_image")
        ]
        for field in ("resource", "h1", "headline", "description"):
            item.pop(field, None)

    # ── структурные страницы (нет HTML, заголовок из translations) ────────────
    for item in structural_pages:
        item["title"] = translations.get_page_title(item["slug"], lang)

    # ── объединяем в исходном порядке ─────────────────────────────────────────
    content_map    = {p["slug"]: p for p in resource_list}
    structural_map = {p["slug"]: p for p in structural_pages}
    all_pages = [
        content_map.get(p["slug"]) or structural_map.get(p["slug"])
        for p in pages
    ]

    # расписание для отложенных публикаций
    scheduled = [p for p in all_pages if p.get("publish_at") == "schedule"]
    if scheduled:
        pattern      = os.environ.get("SCHEDULE_PATTERN", "3d 2-3p (10-21)")
        scheduled    = schedule.enrich_with_schedule(scheduled, pattern)
        schedule_map = {p["slug"]: p["publish_at"] for p in scheduled}
        for item in all_pages:
            if item["slug"] in schedule_map:
                item["publish_at"] = schedule_map[item["slug"]].strftime("%Y-%m-%dT%H:%M:%S")

    return {
        "site":           _site_config(),
        "structure_type": structure["structure_type"],
        "menus":          structure["menus"],
        "pages":          all_pages,
    }


# ─── Шаг 8: валидация манифеста ──────────────────────────────────────────────

REQUIRED_PAGE_FIELDS = ["slug", "title", "publish_at"]

def _validate_manifest(manifest: dict):
    errors = []

    if not manifest.get("site", {}).get("title"):
        errors.append("site.title отсутствует")

    slugs = set()
    for i, page in enumerate(manifest.get("pages", [])):
        prefix = f"pages[{i}] slug={page.get('slug', '?')!r}"
        for field in REQUIRED_PAGE_FIELDS:
            if not page.get(field):
                errors.append(f"{prefix}: отсутствует поле '{field}'")
        slug = page.get("slug")
        if slug in slugs:
            errors.append(f"{prefix}: дублирующийся slug")
        slugs.add(slug)
        content = page.get("content")
        if content:
            content_path = STAGING_DIR / "pages" / content
            if not content_path.exists():
                errors.append(f"{prefix}: .wp файл не найден ({content_path})")

    if errors:
        print("\n  ОШИБКИ В МАНИФЕСТЕ:")
        for e in errors:
            print(f"    ✗ {e}")
        sys.exit(1)

    print(f"  Манифест валиден. Страниц: {len(manifest['pages'])}")


# ─── Конфигурация сайта из .env ──────────────────────────────────────────────

def _site_config() -> dict:
    favicon_path = _find_branding_file(["favicon", "icon"])
    logo_path    = _find_branding_file(["logo"])
    return {
        "title":      os.environ.get("SITE_TITLE",  "WordPress Site"),
        "url":        os.environ.get("SITE_URL",     "http://localhost:8080"),
        "lang":       os.environ.get("SITE_LANG",    "EN"),
        "email":      os.environ.get("ADMIN_EMAIL",  "admin@example.com"),
        "admin_user": os.environ.get("ADMIN_USER",   "admin"),
        "favicon":    Path(favicon_path).name if favicon_path else None,
        "logo":       Path(logo_path).name    if logo_path    else None,
    }


def _find_branding_file(stems: list[str]) -> str | None:
    """Ищет первый подходящий файл брендинга в spec/ по имени и любому формату."""
    exts = [".png", ".webp", ".jpg", ".jpeg", ".svg", ".ico"]
    for stem in stems:
        for ext in exts:
            candidate = SPEC_DIR / f"{stem}{ext}"
            if candidate.exists():
                return str(candidate)
    return None


def _copy_branding_to_build(out_dir: Path):
    """Копирует logo/favicon из spec/ в staging/images/, обрабатывая растровые файлы."""
    import shutil
    from PIL import Image

    for stems, max_kb, max_height in (
        (["favicon", "icon"], 15,  None),
        (["logo"],            50,  100),
    ):
        src = _find_branding_file(stems)
        if not src:
            continue
        src = Path(src)
        dst = out_dir / src.name
        if dst.exists():
            continue

        suffix = src.suffix.lower()
        src_kb = src.stat().st_size / 1024

        # SVG и ICO не трогаем
        if suffix in (".svg", ".ico"):
            shutil.copy2(src, dst)
            print(f"  Скопирован: {src.name}")
            continue

        # Проверяем, нужна ли обработка
        height_ok = (not max_height) or (Image.open(src).height <= max_height)
        if src_kb <= max_kb and height_ok:
            shutil.copy2(src, dst)
            print(f"  Скопирован: {src.name}  ({src_kb:.1f} KB)")
            continue

        result = _process_branding_raster(src, max_kb=max_kb, max_height=max_height)
        if result is None:
            print(f"  [!] Не удалось обработать '{src.name}', скопирован оригинал")
            shutil.copy2(src, dst)
            continue

        shutil.copy2(result, dst)
        dst_kb = dst.stat().st_size / 1024
        info = f"{src_kb:.1f} → {dst_kb:.1f} KB"
        if max_height:
            w, h = Image.open(dst).size
            info += f" | {w}×{h}px"
        print(f"  Брендинг: {src.name}  ({info})")


def _process_branding_raster(src: Path, max_kb: int, max_height: int | None) -> Path | None:
    """Уменьшает высоту (если нужно) и сжимает растровый файл до max_kb.
    Возвращает путь к временному файлу или None если сжать не удалось."""
    import io, tempfile
    from PIL import Image

    try:
        img = Image.open(src)
    except Exception as e:
        print(f"  [!] Ошибка открытия {src.name}: {e}")
        return None

    has_alpha = img.mode in ("RGBA", "LA", "PA") or (
        img.mode == "P" and "transparency" in img.info
    )
    img = img.convert("RGBA" if has_alpha else "RGB")

    # 1. Ограничение высоты (для logo)
    if max_height and img.height > max_height:
        ratio = max_height / img.height
        img = img.resize((max(1, int(img.width * ratio)), max_height), Image.Resampling.LANCZOS)

    target = max_kb * 1024
    suffix = src.suffix.lower()
    tmp = Path(tempfile.mkdtemp()) / src.name
    quality = 90
    resize_f = 1.0

    for _ in range(35):
        buf = io.BytesIO()
        curr = img
        if resize_f < 1.0:
            curr = img.resize(
                (max(1, int(img.width * resize_f)), max(1, int(img.height * resize_f))),
                Image.Resampling.LANCZOS,
            )
        if suffix == ".png":
            colors = max(16, int(256 * quality / 100))
            curr.quantize(colors=colors, method=Image.Quantize.MEDIANCUT).convert("RGBA").save(
                buf, format="PNG", optimize=True
            )
        elif suffix in (".jpg", ".jpeg"):
            curr.convert("RGB").save(buf, format="JPEG", quality=quality, optimize=True)
        elif suffix == ".webp":
            curr.save(buf, format="WEBP", quality=quality, method=6)
        else:
            curr.save(buf, format="PNG", optimize=True)

        if buf.tell() <= target:
            tmp.write_bytes(buf.getvalue())
            return tmp

        if quality > 30:
            quality -= 10
        elif resize_f > 0.4:
            resize_f = round(resize_f - 0.1, 1)
            quality = 90
        else:
            return None

    return None


# ─── Windows-специфичные шаги ────────────────────────────────────────────────

def _install_and_activate_plugin(slug: str):
    container = os.environ.get("CONTAINER_NAME", "wp_site")
    print(f"  Установка плагина '{slug}'...")
    result = subprocess.run(
        ["docker", "exec", "-u", "www-data", "-e", "HOME=/tmp", container,
         "wp", "plugin", "install", slug, "--activate", "--path=/var/www/html"],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        output = (result.stdout + result.stderr).strip()
        print(f"  [!] Не удалось установить плагин '{slug}': {output}")
    else:
        print(f"  Плагин '{slug}' установлен и активирован.")


def _activate_plugin(slug: str):
    container = os.environ.get("CONTAINER_NAME", "wp_site")
    print(f"  Активация плагина '{slug}'...")
    result = subprocess.run(
        ["docker", "exec", "-u", "www-data", "-e", "HOME=/tmp", container,
         "wp", "plugin", "activate", slug, "--path=/var/www/html"],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        output = (result.stdout + result.stderr).strip()
        print(f"  [!] Не удалось активировать плагин: {output}")
    else:
        print(f"  Плагин '{slug}' активирован.")


# ─── Проверка языка ──────────────────────────────────────────────────────────

def _check_lang():
    lang = os.environ.get("SITE_LANG", "").strip().upper()
    if lang in LANG_MAP:
        print(f"  Язык: {lang}")
        return
    valid = ", ".join(sorted(LANG_MAP))
    if lang:
        print(f"\n  ОШИБКА: язык '{lang}' не поддерживается.")
    else:
        print("\n  ОШИБКА: переменная SITE_LANG не задана в .env.")
    print(f"  Доступные языки: {valid}")
    sys.exit(1)


# ─── Утилиты вывода ──────────────────────────────────────────────────────────

def _header(label: str):
    print(f"\n{'=' * 60}")
    print(f"  {label}")
    print(f"{'=' * 60}")

def _phase(n: int, total: int, label: str):
    print(f"\n{'─' * 60}")
    print(f"  [{n}/{total}] {label}")
    print(f"{'─' * 60}")


if __name__ == "__main__":
    run()
