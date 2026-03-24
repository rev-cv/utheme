import os
import io
import shutil
import tempfile
import mimetypes
import requests
from pathlib import Path
from PIL import Image

def convert_to_webp_and_compress(file_path, max_size_kb):
    """Специализированная функция для принудительного WebP"""
    target_size = max_size_kb * 1024
    temp_dir = Path(tempfile.mkdtemp())
    temp_path = temp_dir / file_path.with_suffix('.webp').name

    try:
        img = Image.open(file_path)
        if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
            img = img.convert('RGBA')
        else:
            img = img.convert('RGB')

        quality = 85
        resize_factor = 1.0

        while True:
            buf = io.BytesIO()
            curr_img = img
            if resize_factor < 1.0:
                curr_img = img.resize((int(img.width * resize_factor), int(img.height * resize_factor)), Image.Resampling.LANCZOS)
            
            curr_img.save(buf, format='WEBP', quality=quality, method=6) # method 6 = лучшее сжатие
            
            print(f"    сжимаю {file_path.name} до <{max_size_kb} KB: {buf.tell() / 1024:.2f} KB | Q={quality} | Resize={resize_factor:.2f}", end="\r", flush=True)

            if buf.tell() <= target_size:
                print(f"\r{' ' * 100}\r", end="", flush=True)
                temp_path.write_bytes(buf.getvalue())
                return temp_path
            
            if quality > 25:
                quality -= 10
            elif resize_factor > 0.3:
                resize_factor -= 0.1
                quality = 85
            else:
                print(f"\r{' ' * 100}\r", end="", flush=True)
                return None
    except Exception as e:
        print(f"    Ошибка конвертации в WebP: {e}")
        return None


def ensure_image_size(file_path, max_size_kb):
    """
    Гарантирует, что размер изображения не превышает max_size_kb.
    Если изображение уже меньше, возвращает оригинальный путь.
    Если больше - агрессивно сжимает его, уменьшая качество и разрешение.
    Если сжать не удалось - возвращает None.
    Возвращает путь к временному файлу в случае успешного сжатия.
    """
    target_size = max_size_kb * 1024
    if os.path.getsize(file_path) <= target_size:
        return file_path

    try:
        img = Image.open(file_path)
    except Exception:
        return file_path

    fmt = img.format or ('PNG' if file_path.suffix.lower() == '.png' else 'JPEG')
    
    if fmt.upper() in ('JPEG', 'JPG') and img.mode in ('RGBA', 'P', 'LA'):
        img = img.convert('RGB')

    quality = 90
    resize_factor = 1.0
    temp_dir = Path(tempfile.mkdtemp())
    temp_path = temp_dir / file_path.name

    while True:
        buf = io.BytesIO()
        current_img = img
        if resize_factor < 1.0:
            new_width = int(img.width * resize_factor)
            new_height = int(img.height * resize_factor)
            if new_width < 100 or new_height < 100:
                print(f"\r{' ' * 100}\r", end="", flush=True)
                shutil.rmtree(temp_dir)
                return None
            current_img = img.resize((new_width, new_height), Image.Resampling.LANCZOS)

        if fmt.upper() in ('JPEG', 'JPG', 'WEBP', 'AVIF'):
            current_img.save(buf, format=fmt, quality=quality, optimize=True)
        elif fmt.upper() == 'PNG':
            colors = max(16, int(256 * (quality / 100.0)))
            quantized_img = current_img.convert('RGBA').quantize(colors=colors, method=Image.Quantize.MEDIANCUT)
            quantized_img.save(buf, format='PNG', optimize=True)
        else:
            current_img.save(buf, format=fmt)

        print(f"    сжимаю {file_path.name} до <{max_size_kb} KB: {buf.tell() / 1024:.2f} KB | Q={quality} | Resize={resize_factor:.2f}", end="\r", flush=True)

        if buf.tell() <= target_size:
            print(f"\r{' ' * 100}\r", end="", flush=True)
            with open(temp_path, 'wb') as f:
                f.write(buf.getvalue())
            return temp_path

        if quality > 38:
            quality -= 10
        else:
            resize_factor *= 0.9
            quality = 90

def resize_image(input_path, output_path=None, height=None, size=None):
    """
    Изменяет размер изображения.
    - Если указан height: сохраняет пропорции, подгоняя под высоту.
    - Если указан size (w, h): изменяет размер строго под эти параметры.
    """
    if not output_path:
        directory, filename = os.path.split(input_path)
        new_filename = filename.replace("-lg", "")
        output_path = os.path.join(directory, new_filename)

    try:
        with Image.open(input_path) as img:
            original_width, original_height = img.size
            
            # Логика изменения размера
            if height:
                # Вычисляем пропорциональную ширину
                # Ratio = W_old / H_old
                ratio = original_width / original_height
                new_width = int(height * ratio)
                target_size = (new_width, height)
            elif size:
                target_size = size
            else:
                # Если ничего не указано, оставим как есть или зададим дефолт
                target_size = (100, 100)

            resized_img = img.resize(target_size, Image.Resampling.LANCZOS)
            resized_img.save(output_path)
            print(f"Успех: {input_path} -> {output_path} (Размер: {target_size})")
            
    except Exception as e:
        print(f"Ошибка при обработке {input_path}: {e}")


def search_branding_images(search_root: Path, spec_dir: Path):
    """
    Ищет logo, favicon, icon в папке search_root и копирует/конвертирует их в spec_dir.
    favicon переименовывается в icon.
    Все растровые изображения конвертируются в .png.
    """
    print(f"\nПоиск Branding-изображений (logo, icon) в: {search_root}")
    
    targets = {'logo': 'logo', 'favicon': 'icon', 'icon': 'icon'}
    valid_exts = {'.png', '.jpg', '.jpeg', '.webp', '.svg', '.ico'}
    found_items = {}

    for file_path in search_root.rglob("*"):
        if file_path.is_file() and file_path.suffix.lower() in valid_exts:
            stem = file_path.stem.lower()
            if stem in targets:
                target_name = targets[stem]
                # Сохраняем первый найденный вариант
                if target_name not in found_items:
                    found_items[target_name] = file_path

    if not found_items:
        print("    Брендинг не найден.")
        return

    for target_name, source_file in found_items.items():
        try:
            dest_file = spec_dir / f"{target_name}.png"
            if source_file.suffix.lower() == '.svg':
                shutil.copy2(source_file, spec_dir / f"{target_name}.svg")
                print(f"    Скопирован SVG: {source_file.name} -> {target_name}.svg")
            else:
                with Image.open(source_file) as img:
                    img.convert('RGBA').save(dest_file, format='PNG')
                print(f"    Сконвертирован: {source_file.name} -> {dest_file.name}")
        except Exception as e:
            print(f"    Ошибка обработки {source_file}: {e}")