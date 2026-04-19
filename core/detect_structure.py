import importlib
import sys
from pathlib import Path

_STRUCTURES_DIR = Path(__file__).parent / "structures"


def detect_structure(spec_dir: Path) -> dict:
    """
    Scans core/structures/*.py alphabetically, calls detect(spec_dir) on each,
    and returns build(spec_dir) from the first matching plugin.
    """
    spec_dir = Path(spec_dir)
    plugins  = sorted(
        p for p in _STRUCTURES_DIR.glob("*.py")
        if not p.stem.startswith("_")
    )

    for plugin_path in plugins:
        mod = importlib.import_module(f".structures.{plugin_path.stem}", package="core")
        if mod.detect(spec_dir):
            print(f"  Структура: {plugin_path.stem}")
            result = mod.build(spec_dir)
            print(f"  Страниц:   {len(result['pages'])}")
            return result

    print(f"\nОшибка: неизвестная структура проекта в {spec_dir}")
    print("  Ожидается одна из:")
    print("    • PILLAR + CL1          (struc1 — flat5)")
    print("    • PILLAR + CLUSTERS MAIN (struc2 — clusters_main5)")
    print("    • HUB + site-structure.txt (struc3 — hub_pillar)")
    sys.exit(1)
