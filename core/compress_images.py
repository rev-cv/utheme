import io
import os
import shutil
import tempfile
import threading
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from PIL import Image
from rich.console import Group
from rich.live import Live
from rich.padding import Padding
from rich.progress import (
    BarColumn, MofNCompleteColumn, Progress, TaskProgressColumn, TextColumn, TimeElapsedColumn,
)
from rich.table import Table
from rich.text import Text

from core.console import (
    ACCENT_STYLE, ACTION_INDENT, ACTION_STYLE, BAR_BACK_STYLE, BAR_DONE_STYLE,
    ERROR_STYLE, MUTED_STYLE, RESULT_INDENT, SUCCESS_STYLE, WARNING_STYLE,
    TABLE_BORDER_STYLE,
)
from core.console import console, result


def compress_images(pics: list[dict], out_dir: Path, max_kb: int) -> None:
    # Больше восьми одновременных строк перегружают Live-таблицу и обычно не
    # ускоряют Pillow: кодирование WebP само активно использует CPU.
    n_workers = min(os.cpu_count() or 1, 8)

    valid   = [p for p in pics if p.get("selected_image") and Path(p["selected_image"]).exists()]
    n_slots = min(n_workers, len(valid))

    if not n_slots:
        result("Нет изображений для обработки.")
        return

    thread_slots: dict[int, int] = {}
    state_lock = threading.Lock()

    def get_slot() -> int:
        tid = threading.get_ident()
        with state_lock:
            if tid not in thread_slots:
                thread_slots[tid] = len(thread_slots)
        return thread_slots[tid]

    counters = {"done": 0, "skipped": 0, "errors": 0, "before_kb": 0.0, "after_kb": 0.0}
    records: list[tuple[str, float, float, str]] = []  # (name, before_kb, after_kb, status)
    slot_rows = [{"name": "", "before": "", "after": "", "status": ""} for _ in range(n_slots)]

    overall = Progress(
        TextColumn(f"[{ACTION_STYLE}]{ACTION_INDENT}{{task.description}}"),
        BarColumn(
            style=BAR_BACK_STYLE,
            complete_style=BAR_DONE_STYLE,
            finished_style=BAR_DONE_STYLE,
            pulse_style=ACCENT_STYLE,
        ),
        TaskProgressColumn(style=SUCCESS_STYLE),
        # В этой версии Rich цвет MofNCompleteColumn задаётся темой
        # progress.download, а конструктор не принимает style=.
        MofNCompleteColumn(),
        TextColumn("•", style=MUTED_STYLE),
        TimeElapsedColumn(),
        console=console,
    )
    overall_task = overall.add_task("Обработка файлов", total=len(valid))

    def make_table() -> Table:
        # expand=True отдаёт Rich точную ширину терминала. Фиксированная ширина
        # имени файла раньше переполняла узкие окна и ломала перерисовку Live.
        table = Table(
            box=None,
            padding=(0, 1),
            pad_edge=False,
            expand=True,
            header_style=ACCENT_STYLE,
        )
        table.add_column("Файл", ratio=1, no_wrap=True, overflow="ellipsis")
        table.add_column("До", justify="right", width=10, no_wrap=True)
        table.add_column("После", justify="right", width=10, no_wrap=True)
        table.add_column("Статус", width=20, no_wrap=True, overflow="ellipsis")
        for row in slot_rows:
            # Пустые строки намеренно рендерятся с первого кадра. Постоянная
            # высота блока позволяет Live корректно стереть его при resize.
            table.add_row(row["name"] or "", row["before"], row["after"], row["status"])
        return table

    def make_summary() -> Text:
        saved = counters["before_kb"] - counters["after_kb"]
        pct   = (saved / counters["before_kb"] * 100) if counters["before_kb"] else 0.0
        style = ERROR_STYLE if counters["errors"] else SUCCESS_STYLE
        return Text(
            f"{RESULT_INDENT}Сжато {counters['done']} картинок. Сэкономлено {saved:.1f} KB ({pct:.0f}%). "
            f"Пропущено: {counters['skipped']}  |  Ошибок: {counters['errors']}",
            style=style,
            no_wrap=True,
            overflow="ellipsis",
        )

    def redraw() -> None:
        live_display.update(Group(overall, Padding(make_table(), (0, 0, 0, 4)), make_summary()))

    def process(pic: dict) -> None:
        src = pic.get("selected_image")
        if not src or not Path(src).exists():
            return

        slot   = get_slot()
        src    = Path(src)
        src_kb = src.stat().st_size / 1024
        name   = src.name

        with state_lock:
            slot_rows[slot].update(name=name, before=f"{src_kb:.1f} KB", after="", status="...")
            redraw()

        # SVG не конвертируется в WebP — копируем как есть
        if src.suffix.lower() == '.svg':
            dst = out_dir / src.name
            if not dst.exists():
                shutil.copy2(src, dst)
            dst_kb = dst.stat().st_size / 1024
            pic["selected_image"] = dst
            with state_lock:
                slot_rows[slot].update(after=f"{dst_kb:.1f} KB", status="svg")
                counters["done"] += 1
                counters["before_kb"] += src_kb
                counters["after_kb"]  += dst_kb
                records.append((name, src_kb, dst_kb, "svg"))
                overall.advance(overall_task)
                redraw()
            return

        dst = out_dir / src.with_suffix(".webp").name

        if dst.exists():
            dst_kb = dst.stat().st_size / 1024
            pic["selected_image"] = dst
            with state_lock:
                slot_rows[slot].update(after=f"{dst_kb:.1f} KB", status="пропуск")
                counters["skipped"] += 1
                records.append((name, src_kb, dst_kb, "пропуск"))
                overall.advance(overall_task)
                redraw()
            return

        if src_kb < max_kb and src.suffix.lower() == ".webp":
            shutil.copy2(src, dst)
            dst_kb = dst.stat().st_size / 1024
            pic["selected_image"] = dst
            with state_lock:
                slot_rows[slot].update(after=f"{dst_kb:.1f} KB", status="копия")
                counters["done"] += 1
                counters["before_kb"] += src_kb
                counters["after_kb"]  += dst_kb
                records.append((name, src_kb, dst_kb, "копия"))
                overall.advance(overall_task)
                redraw()
            return

        def on_progress(status_text: str) -> None:
            with state_lock:
                slot_rows[slot].update(status=status_text)
                redraw()

        tmp = _convert_to_webp(src, max_kb, on_progress=on_progress)

        if tmp and Path(tmp).exists():
            dst_kb  = Path(tmp).stat().st_size / 1024
            tmp_dir = Path(tmp).parent
            shutil.move(str(tmp), dst)
            shutil.rmtree(tmp_dir, ignore_errors=True)
            pic["selected_image"] = dst
            with state_lock:
                slot_rows[slot].update(after=f"{dst_kb:.1f} KB", status="ok")
                counters["done"] += 1
                counters["before_kb"] += src_kb
                counters["after_kb"]  += dst_kb
                records.append((name, src_kb, dst_kb, "ok"))
                overall.advance(overall_task)
                redraw()
        else:
            with state_lock:
                slot_rows[slot].update(after="-", status="ошибка")
                counters["errors"] += 1
                records.append((name, src_kb, 0.0, "ошибка"))
                overall.advance(overall_task)
                redraw()

    with Live(console=console, refresh_per_second=10, transient=True) as live_display:
        with ThreadPoolExecutor(max_workers=n_workers) as executor:
            list(executor.map(process, valid))

    _print_final_table(records)


def _print_final_table(records: list[tuple[str, float, float, str]]) -> None:
    if not records:
        return

    table = Table(header_style="bold", border_style=TABLE_BORDER_STYLE)
    table.add_column("Файл", overflow="fold")
    table.add_column("До", justify="right")
    table.add_column("После", justify="right")
    table.add_column("Экономия", justify="right")
    table.add_column("Статус", justify="center")

    status_style = {
        "ok": SUCCESS_STYLE,
        "svg": SUCCESS_STYLE,
        "копия": SUCCESS_STYLE,
        "пропуск": WARNING_STYLE,
        "ошибка": ERROR_STYLE,
    }
    # "пропуск" переиспользует файл из предыдущего запуска — его размер не связан
    # со сжатием в ЭТОМ прогоне, поэтому в экономию/итоги его не засчитываем.
    counted = {"ok", "svg", "копия"}

    total_before = total_after = 0.0
    for name, before_kb, after_kb, status in sorted(records, key=lambda r: r[0].lower()):
        if status in counted:
            total_before += before_kb
            total_after  += after_kb
        saved_pct = ((before_kb - after_kb) / before_kb * 100) if before_kb and status in counted else None
        table.add_row(
            name,
            f"{before_kb:.1f} KB",
            f"{after_kb:.1f} KB" if status in counted else "—",
            f"{saved_pct:.0f}%" if saved_pct is not None else "—",
            status,
            style=status_style.get(status),
        )

    console.print(Padding(table, (0, 0, 0, 4)))

    total_saved = total_before - total_after
    total_pct   = (total_saved / total_before * 100) if total_before else 0.0
    result(
        f"Итого: {len(records)} файлов. До: {total_before:.1f} KB → После: {total_after:.1f} KB "
        f"(сэкономлено {total_saved:.1f} KB, {total_pct:.0f}%)",
        style="green",
    )


def _convert_to_webp(file_path: Path, max_size_kb: int, on_progress=None) -> Path | None:
    target_size = max_size_kb * 1024
    tmp_dir     = Path(tempfile.mkdtemp())
    temp_path   = tmp_dir / file_path.with_suffix('.webp').name

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
                on_progress(f"{current_kb:.0f}KB Q={quality} R={resize_factor:.2f}")

            if buf.tell() <= target_size:
                temp_path.write_bytes(buf.getvalue())
                return temp_path

            if quality > 25:
                quality -= 10
            elif resize_factor > 0.3:
                resize_factor -= 0.1
                quality = 85
            else:
                shutil.rmtree(tmp_dir, ignore_errors=True)
                return None

    except Exception:
        shutil.rmtree(tmp_dir, ignore_errors=True)
        return None
