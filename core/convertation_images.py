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

        if quality > 25:
            quality -= 10
        else:
            resize_factor *= 0.9
            quality = 85