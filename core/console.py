"""
Общий rich-консоль для всего пайплайна.

markup=False — сознательное решение, не косметика: сообщения по всему пайплайну
свободно интерполируют произвольный текст (пути, stderr от docker/wp-cli, куски
HTML, regex-совпадения). Если бы markup был включён, любая подстрока вида
"[что-то]" в этом тексте тихо трактовалась бы rich как тег стиля и вырезалась бы
из вывода без предупреждения (проверено эмпирически: "[!] warning with
[brackets]" превращается в "[!] warning with " — кусок пропадает молча).
Поэтому вся раскраска идёт только через параметр style=, который применяется
к строке целиком и не парсит её содержимое.

Паттерн вывода по всему пайплайну: action() — что делается (янтарным,
без отступа), result() — чем закончилось (с отступом в 4 пробела, цвет зависит
от исхода: бирюзовый — успех, янтарный — некритичное предупреждение, красный — ошибка,
без style — нейтральная информация).
"""

import sys

from rich.console import Console
from rich.theme import Theme

# Пайплайн печатает не-ASCII символы (─, →, ✓, —) напрямую. Если stdout/stderr
# не подключены к реальному терминалу (перенаправление в файл, `| tee log.txt`,
# CI-раннер), Python на Windows может унаследовать кодировку консоли (например
# cp1251) вместо UTF-8 — тогда запись такого символа роняет процесс
# UnicodeEncodeError. reconfigure() с errors="replace" — подстраховка на этот
# случай; в реальном интерактивном терминале ничего не меняет.
try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

# Спокойная палитра в духе Tokyo Night. highlight=False отключает встроенную
# подсветку путей/чисел Rich, из-за которой отдельные фрагменты внезапно
# становились фиолетовыми или ярко-синими.
HEADER_STYLE  = "bold #7aa2f7"
ACCENT_STYLE  = "#7dcfff"
PHASE_STYLE   = "bold #9ece6a"
ACTION_STYLE  = "bold #e0af68"
SUCCESS_STYLE = ACCENT_STYLE
WARNING_STYLE = "#e0af68"
ERROR_STYLE   = "bold #f7768e"
MUTED_STYLE   = "#565f89"
BAR_BACK_STYLE = "#24283b"
BAR_DONE_STYLE = ACCENT_STYLE
TABLE_BORDER_STYLE = "#6b7280"

ACTION_INDENT = "  "
RESULT_INDENT = "      "

console = Console(
    markup=False,
    highlight=False,
    theme=Theme({
        "progress.elapsed": MUTED_STYLE,
        "progress.percentage": SUCCESS_STYLE,
        "progress.download": ACCENT_STYLE,
    }),
)


_LEGACY_STYLES = {
    "green": SUCCESS_STYLE,
    "yellow": WARNING_STYLE,
    "bold red": ERROR_STYLE,
}


def header(label: str) -> None:
    console.print()
    console.rule(label, style=HEADER_STYLE, align="left")


def phase(n: int, total: int, label: str) -> None:
    console.print()
    console.print(f"[{n}/{total}] {label}", style=PHASE_STYLE)


def action(msg: str) -> None:
    console.print(f"{ACTION_INDENT}{msg}", style=ACTION_STYLE)


def result(msg: str, style: str | None = None) -> None:
    resolved_style = SUCCESS_STYLE if style is None else _LEGACY_STYLES.get(style, style)
    console.print(f"{RESULT_INDENT}{msg}", style=resolved_style)


def warn(msg: str) -> None:
    console.print(f"{RESULT_INDENT}[!] {msg}", style=WARNING_STYLE)


def error(msg: str) -> None:
    console.print(f"{RESULT_INDENT}[!] {msg}", style=ERROR_STYLE)
