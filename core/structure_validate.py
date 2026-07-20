from pathlib import Path

from core.console import console, action, result, error


def check_structure_flexible(root_directory, required_items) -> list[str]:
    """
    Проверяет наличие обязательных элементов структуры проекта.
    Возвращает список строк с описанием отсутствующих элементов (пустой при успехе).

    required_items может содержать:
      - str / Path          — обычная проверка существования
      - str с '*'           — glob-шаблон (напр. "CL*")
      - list[Path]          — список альтернатив (достаточно одного)
    """
    action(f"Проверка структуры проекта в: {root_directory}")

    root_path     = Path(root_directory)
    missing_items = []

    for item in required_items:
        if isinstance(item, list):
            if not any(p.exists() for p in item):
                paths_str = "\n".join(
                    f"            - {_rel(p)}" for p in item
                )
                missing_items.append(
                    f"Не найден ни один из обязательных элементов:\n{paths_str}"
                )
            continue

        item_str = str(item)

        if '*' in item_str:
            if not list(root_path.glob(item_str)):
                missing_items.append(f"Элемент по шаблону: {item_str}")
            continue

        target_path = root_path / item
        if not target_path.exists():
            missing_items.append(f"Отсутствует: {_rel(target_path)}")

    if missing_items:
        error("Структура проекта не соответствует требованиям!")
        for e in missing_items:
            console.print(f"        {e}")
        return missing_items

    result("Общая структура проекта подтверждена.", style="green")
    return []


def _rel(path: Path) -> Path:
    try:
        return path.relative_to(Path.cwd())
    except ValueError:
        return path
