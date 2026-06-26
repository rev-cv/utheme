import io
import shutil
import tempfile
from pathlib import Path

from PIL import Image

_STEMS_FAVICON = ["favicon", "icon"]
_STEMS_LOGO    = ["logo"]
_EXTS          = [".png", ".webp", ".jpg", ".jpeg", ".svg", ".ico"]


def find_branding_file(spec_dir: Path, stems: list[str]) -> str | None:
    for stem in stems:
        for ext in _EXTS:
            candidate = spec_dir / f"{stem}{ext}"
            if candidate.exists():
                return str(candidate)
    return None


def staging_name(src: Path) -> str:
    """Return the filename that copy_branding_to_build() writes to staging/images/."""
    if src.suffix.lower() == ".ico":
        return src.with_suffix(".png").name
    return src.name


def copy_branding_to_build(spec_dir: Path, out_dir: Path) -> None:
    for stems, max_kb, max_height in (
        (_STEMS_FAVICON, 15,  64),
        (_STEMS_LOGO,    50, 100),
    ):
        src = find_branding_file(spec_dir, stems)
        if not src:
            continue
        src = Path(src)
        suffix = src.suffix.lower()
        dst = out_dir / staging_name(src)
        if dst.exists():
            continue

        src_kb = src.stat().st_size / 1024

        if suffix == ".svg":
            shutil.copy2(src, dst)
            print(f"  Скопирован: {src.name}")
            continue

        if suffix == ".ico":
            _convert_ico_to_png(src, dst, max_height)
            continue

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


def _convert_ico_to_png(src: Path, dst: Path, max_height: int | None) -> None:
    try:
        img = Image.open(src)
        if hasattr(img, "ico") and img.ico.sizes:
            best_size = max(img.ico.sizes, key=lambda s: s[0] * s[1])
            img = img.ico.getimage(best_size)
        img = img.convert("RGBA")
        if max_height and img.height > max_height:
            ratio = max_height / img.height
            img = img.resize(
                (max(1, int(img.width * ratio)), max_height),
                Image.Resampling.LANCZOS,
            )
        img.save(dst, format="PNG", optimize=True)
        src_kb = src.stat().st_size / 1024
        dst_kb = dst.stat().st_size / 1024
        print(f"  Favicon: {src.name} → {dst.name}  ({src_kb:.1f} → {dst_kb:.1f} KB)")
    except Exception as e:
        print(f"  [!] Ошибка конвертации ICO {src.name}: {e}")


def _process_branding_raster(src: Path, max_kb: int, max_height: int | None) -> Path | None:
    try:
        img = Image.open(src)
    except Exception as e:
        print(f"  [!] Ошибка открытия {src.name}: {e}")
        return None

    has_alpha = img.mode in ("RGBA", "LA", "PA") or (
        img.mode == "P" and "transparency" in img.info
    )
    img = img.convert("RGBA" if has_alpha else "RGB")

    if max_height and img.height > max_height:
        ratio = max_height / img.height
        img = img.resize((max(1, int(img.width * ratio)), max_height), Image.Resampling.LANCZOS)

    target   = max_kb * 1024
    suffix   = src.suffix.lower()
    tmp      = Path(tempfile.mkdtemp()) / src.name
    quality  = 90
    resize_f = 1.0

    for _ in range(35):
        buf  = io.BytesIO()
        curr = img
        if resize_f < 1.0:
            curr = img.resize(
                (max(1, int(img.width * resize_f)), max(1, int(img.height * resize_f))),
                Image.Resampling.LANCZOS,
            )
        if suffix == ".png":
            colors = max(16, int(256 * quality / 100))
            curr.quantize(colors=colors, method=Image.Quantize.FASTOCTREE).convert("RGBA").save(
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
            quality  = 90
        else:
            return None

    return None
