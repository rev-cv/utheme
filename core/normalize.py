import re
from pathlib import Path

_BRANDING_EXTS = {".png", ".webp", ".jpg", ".jpeg", ".svg", ".ico"}
_LOGO_STEMS    = {"logo"}
_FAVICON_STEMS = {"favicon", "icon"}

# Правила переименования.
# Формат: "Финальное_Имя": ["вариант1", "вариант2", ...]
# Поиск без учёта регистра (case-insensitive).
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
        "aviso-legal.html",
        "juridisk-information.html",
        "juridisk-informasjon.html",
    ],
    "privacy-policy.html": [
        "politica-de-privacidad.html",
        "politica-privacidad.html",
        "Datenschutzrichtlinie.html",
        "datenschutzerklaerung.html",
        "politique-de-confidentialite.html",
        "privacy.html",
        "privacybeleid.html",
        "datenschutz.html",
        "privatlivspolitik.html",
        "integritetspolicy.html",
        "ochrana-soukromi.html",
    ],
    "about-us.html": [
        "sobre-nosotros.html",
        "Über-uns.html",
        "ueber-uns.html",
        "Uber-uns.html",
        "a-propos-de-nous.html",
        "chi-siamo.html",
        "over-ons.html",
        "about.html",
        "om-os.html",
        "om-oss.html",
        "o-nas.html",
        "a-propos.html",
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
        "cookiepolitik.html",
        "kakspolitik.html",
    ],
    "ADD PAGES": [
        "PAGES SUPPLÉMENTAIRES",
        "ΒΟΗΘΗΤΙΚΕΣ ΣΕΛΙΔΕΣ",
    ],
}


def normalize_branding_assets(spec_dir: Path):
    """Перемещает logo.* и favicon/icon.* из PILLAR/ в корень spec/. Удаляет 404.html."""
    spec_dir = Path(spec_dir)
    print(f"\nНормализация брендинга в: {spec_dir}")

    candidate_dirs = [spec_dir / "PILLAR", spec_dir / "HUB" / "PILLAR"]
    pillar_dir = next((d for d in candidate_dirs if d.is_dir()), None)

    if pillar_dir:
        page_404 = pillar_dir / "404.html"
        if page_404.exists():
            page_404.unlink()
            print(f"  Удалён: {page_404.relative_to(spec_dir)}")

    if pillar_dir is None:
        print("  PILLAR/ не найдена, пропуск.")
        print('=' * 50)
        return

    moved = 0
    for file in list(pillar_dir.iterdir()):
        if not file.is_file() or file.suffix.lower() not in _BRANDING_EXTS:
            continue

        stem = file.stem.lower()
        is_logo    = stem in _LOGO_STEMS or stem.startswith("logo")
        is_favicon = stem in _FAVICON_STEMS or stem.startswith("favicon")

        if not (is_logo or is_favicon):
            continue

        dst = spec_dir / file.name
        if dst.exists():
            continue

        file.rename(dst)
        print(f"  Перемещён: {file.relative_to(spec_dir)} → {file.name}")
        moved += 1

    if moved == 0:
        print("  Брендинг: файлы уже на месте или не найдены в PILLAR/")

    print('=' * 50)


def bulk_rename(directory):
    """Массовое переименование файлов по правилам RENAME_RULES (рекурсивно)."""
    root_path = Path(directory)
    print(f"\nЗапуск массового переименования ФАЙЛОВ:")

    if not root_path.exists():
        print(f"Папка не найдена: {root_path}")
        return

    lookup_map = {
        variant.lower(): final_name
        for final_name, variants in RENAME_RULES.items()
        for variant in variants
    }

    renamed_count = 0
    for file_path in root_path.rglob('*'):
        if not file_path.is_file():
            continue

        current_name       = file_path.name
        current_name_lower = current_name.lower()

        if current_name_lower not in lookup_map:
            continue

        target_name = lookup_map[current_name_lower]
        if current_name == target_name:
            continue

        target_path = file_path.with_name(target_name)
        if target_path.exists() and target_path.resolve() != file_path.resolve():
            if target_path.name.lower() != file_path.name.lower():
                print(f"Пропуск: {current_name} -> {target_name}. Файл {target_name} уже существует.")
                continue

        try:
            file_path.rename(target_path)
            print(f"{file_path.parent.name}/{current_name} -> {target_name}")
            renamed_count += 1
        except OSError as e:
            print(f"Ошибка при переименовании {file_path}: {e}")

    print(f"Готово. Переименовано файлов: {renamed_count}")
    print('\n' + '=' * 50)


def bulk_rename_folders(directory):
    """Массовое переименование папок по правилам RENAME_RULES (от глубоких к корневым)."""
    root_path = Path(directory)

    if not root_path.exists():
        return

    lookup_map = {
        variant.lower(): final_name
        for final_name, variants in RENAME_RULES.items()
        for variant in variants
    }

    dirs_to_process = sorted(
        [p for p in root_path.rglob('*') if p.is_dir()],
        key=lambda p: len(p.parts),
        reverse=True,
    )

    print(f"\nЗапуск массового переименования ПАПОК:")
    renamed_count = 0

    for folder_path in dirs_to_process:
        current_name = folder_path.name
        if current_name.lower() not in lookup_map:
            continue

        target_name = lookup_map[current_name.lower()]
        if current_name == target_name:
            continue

        try:
            folder_path.rename(folder_path.with_name(target_name))
            print(f"{folder_path.parent.name}/{current_name} -> {target_name}")
            renamed_count += 1
        except OSError as e:
            print(f"Ошибка при переименовании папки {folder_path}: {e}")

    print(f"Готово. Переименовано папок: {renamed_count}")
    print('\n' + '=' * 50)


def normalize_all_html_in_directory(directory: Path):
    """Рекурсивно удаляет пробелы перед двоеточием во всех .html файлах."""
    root_path = Path(directory)
    print(f"\nЗапуск нормализации HTML файлов в: {root_path}")

    if not root_path.exists():
        print(f"    Папка не найдена: {root_path}")
        return

    cleaned_count = 0
    for file_path in root_path.rglob('*.html'):
        if _clean_html_spacing(file_path):
            cleaned_count += 1

    if cleaned_count > 0:
        print(f"Готово. Нормализовано файлов: {cleaned_count}")
        print('\n' + '=' * 50)


def _clean_html_spacing(file_path: Path) -> bool:
    try:
        content = file_path.read_text(encoding='utf-8')
        cleaned = re.sub(r'\s+(?=:)', '', content)
        if content != cleaned:
            file_path.write_text(cleaned, encoding='utf-8')
            return True
    except Exception as e:
        print(f"    Ошибка при обработке {file_path}: {e}")
    return False
