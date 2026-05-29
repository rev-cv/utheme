import importlib
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
            return mod.build(spec_dir)

    raise RuntimeError(
        f"Неизвестная структура проекта в {spec_dir}\n"
        "  Ожидается одна из:\n"
        "    • PILLAR + CL1           (s1_cl5_2025)\n"
        "    • PILLAR + CLUSTERS MAIN (s2_cl5_2026)\n"
        "    • HUB + PILLAR           (s3_fwc_2026)\n"
        "    • index.html + slug/     (s4_fsr_2026)\n"
        "    • hub/ + slug/           (s5_minireview_2026)"
    )
