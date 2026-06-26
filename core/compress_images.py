import io
import shutil
import os
import sys
import tempfile
import threading
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from PIL import Image


def compress_images(pics: list[dict], out_dir: Path, max_kb: int) -> None:
    col       = 42
    n_workers = os.cpu_count()

    valid   = [p for p in pics if p.get("selected_image") and Path(p["selected_image"]).exists()]
    n_slots = min(n_workers, len(valid))

    print(f"\n  {'Файл':<{col}} {'До':>8}   {'После':>8}   Статус")
    print(f"  {'─'*col} {'─'*8}   {'─'*8}   {'─'*8}")

    if not n_slots:
        print(f"\n  Нет изображений для обработки.")
        return

    states: list[tuple[str, float, str, str]] = [("", 0.0, "", "—")] * n_slots
    lock = threading.Lock()

    thread_slots: dict[int, int] = {}
    next_slot        = [0]
    slot_assign_lock = threading.Lock()

    def get_slot() -> int:
        tid = threading.get_ident()
        with slot_assign_lock:
            if tid not in thread_slots:
                thread_slots[tid] = next_slot[0]
                next_slot[0] += 1
        return thread_slots[tid]

    for _ in range(n_slots):
        print()

    def _draw_slots() -> None:
        for name, src_kb, after_str, status in states:
            line = f"  {name:<{col}} {src_kb:>6.1f} KB   {after_str:>8}   {status}"
            sys.stdout.write(f"\r\033[2K{line[:120]}\n")

    def redraw() -> None:
        sys.stdout.write(f"\033[{n_slots}A")
        _draw_slots()
        sys.stdout.flush()

    def emit_completed(line_text: str) -> None:
        sys.stdout.write(f"\033[{n_slots}A")
        sys.stdout.write("\033[L")
        sys.stdout.write(f"\r{line_text[:120]}\n")
        _draw_slots()
        sys.stdout.flush()

    counters = {"done": 0, "skipped": 0, "errors": 0}

    def process(pic: dict) -> None:
        src = pic.get("selected_image")
        if not src or not Path(src).exists():
            return

        slot   = get_slot()
        src    = Path(src)
        dst    = out_dir / src.with_suffix(".webp").name
        src_kb = src.stat().st_size / 1024
        name   = src.name[:col]

        with lock:
            states[slot] = (name, src_kb, "", "→ ...")
            redraw()

        if dst.exists():
            dst_kb = dst.stat().st_size / 1024
            pic["selected_image"] = dst
            with lock:
                states[slot] = (name, src_kb, f"{dst_kb:.1f} KB", "пропуск")
                counters["skipped"] += 1
                emit_completed(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   пропуск")
            return

        if src_kb < max_kb and src.suffix.lower() == ".webp":
            shutil.copy2(src, dst)
            dst_kb = dst.stat().st_size / 1024
            pic["selected_image"] = dst
            with lock:
                states[slot] = (name, src_kb, f"{dst_kb:.1f} KB", "копия")
                counters["done"] += 1
                emit_completed(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   копия")
            return

        def on_progress(status_text: str) -> None:
            with lock:
                states[slot] = (name, src_kb, "", status_text)
                redraw()

        tmp = _convert_to_webp(src, max_kb, on_progress=on_progress)

        if tmp and Path(tmp).exists():
            dst_kb = Path(tmp).stat().st_size / 1024
            shutil.move(str(tmp), dst)
            pic["selected_image"] = dst
            with lock:
                states[slot] = (name, src_kb, f"{dst_kb:.1f} KB", "✓")
                counters["done"] += 1
                emit_completed(f"  {name:<{col}} {src_kb:>6.1f} KB   {dst_kb:>6.1f} KB   ✓")
        else:
            with lock:
                states[slot] = (name, src_kb, "—", "ошибка")
                counters["errors"] += 1
                emit_completed(f"  {name:<{col}} {src_kb:>6.1f} KB   {'—':>8}   ошибка")

    with ThreadPoolExecutor(max_workers=n_workers) as executor:
        list(executor.map(process, pics))

    with lock:
        sys.stdout.write(f"\033[{n_slots}A\033[{n_slots}M")
        sys.stdout.flush()

    print(f"\n  Сжато: {counters['done']}  |  Пропущено (уже есть): {counters['skipped']}  |  Ошибок: {counters['errors']}")


def _convert_to_webp(file_path: Path, max_size_kb: int, on_progress=None) -> Path | None:
    target_size = max_size_kb * 1024
    temp_path   = Path(tempfile.mkdtemp()) / file_path.with_suffix('.webp').name

    try:
        img = Image.open(file_path)
        if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
            img = img.convert('RGBA')
        else:
            img = img.convert('RGB')

        quality      = 85
        resize_factor = 1.0

        while True:
            buf      = io.BytesIO()
            curr_img = img
            if resize_factor < 1.0:
                curr_img = img.resize(
                    (int(img.width * resize_factor), int(img.height * resize_factor)),
                    Image.Resampling.LANCZOS,
                )
            curr_img.save(buf, format='WEBP', quality=quality, method=6)

            current_kb = buf.tell() / 1024
            if on_progress:
                on_progress(f"→ {current_kb:.0f}KB Q={quality} R={resize_factor:.2f}")

            if buf.tell() <= target_size:
                temp_path.write_bytes(buf.getvalue())
                return temp_path

            if quality > 25:
                quality -= 10
            elif resize_factor > 0.3:
                resize_factor -= 0.1
                quality = 85
            else:
                return None

    except Exception:
        return None
