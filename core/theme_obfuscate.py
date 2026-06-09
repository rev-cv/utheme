"""
Theme CSS class obfuscation.

Workflow:
1. git restore utheme/  — reset to canonical ut- prefixed state
2. make_class_map(site_url)  → deterministic per-domain class name mapping
3. apply in-place to all .php / .css / .scss files in utheme/

Class variants live in core/keyclass/keyclass-*.yml.
Each file is a YAML mapping of canonical "ut-" class name → list of N themed
synonyms.  All files are merged at import time; every theme column is validated
for uniqueness, so a collision causes a loud error before any deployment runs.

make_class_map() picks a theme index (0..N-1) via sha256(domain) % N,
producing a bijective mapping for that site — collisions are structurally
impossible within any single theme.

Adding a new canonical class:
  1. Append the key + N synonyms to the appropriate keyclass-*.yml.
  2. Ensure each new synonym is unique within its theme column (the loader
     will reject duplicates at startup).
"""

from __future__ import annotations

import hashlib
import re
import subprocess
from pathlib import Path
from urllib.parse import urlparse

import yaml

# Valid CSS class name: lowercase letter, then lowercase letters/digits/hyphens.
_CSS_NAME_RE = re.compile(r"^[a-z][a-z0-9-]*$")
# Canonical key must start with "ut-" then be a valid CSS name.
_KEY_RE = re.compile(r"^ut-[a-z][a-z0-9-]*$")
# Extracts bare block names from source (stops before __ BEM element separator).
_UT_CLASS_RE = re.compile(r"\but-[a-z][a-z0-9-]*")


_KEYCLASS_DIR = Path(__file__).parent / "keyclass"


# ── Load and validate at import time ─────────────────────────────────────────

def _load_variants() -> dict[str, list[str]]:
    yml_files = sorted(_KEYCLASS_DIR.glob("keyclass-*.yml"))
    if not yml_files:
        raise FileNotFoundError(f"No keyclass-*.yml files found in {_KEYCLASS_DIR}")

    merged: dict[str, list[str]] = {}
    for path in yml_files:
        data: dict = yaml.safe_load(path.read_text(encoding="utf-8")) or {}
        for key, variants in data.items():
            if key in merged:
                raise ValueError(
                    f"Duplicate canonical key {key!r} "
                    f"(already defined, conflict in {path.name})"
                )
            # ── Key format ─────────────────────────────────────────────────
            if not _KEY_RE.match(key):
                raise ValueError(
                    f"{path.name}: key {key!r} must start with 'ut-' "
                    f"and contain only lowercase letters, digits, and hyphens"
                )
            if not isinstance(variants, list) or not variants:
                raise ValueError(
                    f"{path.name}: key {key!r} must map to a non-empty list"
                )
            # ── Value format ───────────────────────────────────────────────
            for i, v in enumerate(variants):
                v = str(v)
                if v.startswith("ut-"):
                    raise ValueError(
                        f"{path.name}: key {key!r}, variant {i} ({v!r}) "
                        f"must not start with 'ut-' — obfuscated names cannot keep the canonical prefix"
                    )
                if not _CSS_NAME_RE.match(v):
                    raise ValueError(
                        f"{path.name}: key {key!r}, variant {i} ({v!r}) "
                        f"is not a valid CSS class name (lowercase letters, digits, hyphens only)"
                    )
            merged[key] = [str(v) for v in variants]

    if not merged:
        raise ValueError("No class variants loaded from keyclass-*.yml files")

    # All variant lists must be the same length
    lengths = {len(v) for v in merged.values()}
    if len(lengths) != 1:
        offenders = {k: len(v) for k, v in merged.items() if len(v) != min(lengths)}
        raise ValueError(
            f"All keys must have the same number of variants. "
            f"Expected {min(lengths)}, got different counts for: {offenders}"
        )

    n = lengths.pop()

    # Within each theme column, all names must be unique
    for col in range(n):
        seen: dict[str, str] = {}
        for key, variants in merged.items():
            name = variants[col]
            if name in seen:
                raise ValueError(
                    f"Theme {col}: obfuscated name {name!r} is used by both "
                    f"{seen[name]!r} and {key!r}"
                )
            seen[name] = key

    return merged


_VARIANTS: dict[str, list[str]] = _load_variants()
_N_THEMES: int = len(next(iter(_VARIANTS.values())))


# ── Coverage check ────────────────────────────────────────────────────────────

def check_keyclass_coverage(theme_dir: Path) -> list[str]:
    """
    Scan PHP/SCSS/CSS files in theme_dir for 'ut-*' class names and report
    any that are not covered by a keyclass entry.

    BEM elements (ut-block__elem) are covered automatically by their block key
    because the regex stops at '__'. BEM modifiers (ut-block--mod) are checked
    by stripping the '--modifier' suffix and looking up the block key.

    Returns a sorted list of uncovered canonical names (may be empty).
    """
    keys = set(_VARIANTS.keys())
    found: set[str] = set()

    for path in sorted(theme_dir.rglob("*")):
        if path.suffix in {".php", ".scss", ".css"} and path.is_file():
            text = path.read_text(encoding="utf-8")
            for m in _UT_CLASS_RE.finditer(text):
                found.add(m.group())

    uncovered: list[str] = []
    for cls in sorted(found):
        # obfuscate_theme uses longest-first substring replace, so any key that
        # is a prefix of cls means cls will be transformed at deploy time.
        if any(cls.startswith(k) for k in keys):
            continue
        uncovered.append(cls)

    return uncovered


# ── Public API ────────────────────────────────────────────────────────────────

def make_class_map(site_url: str) -> dict[str, str]:
    """Return deterministic mapping ut-canonical → obfuscated name, seeded by domain."""
    domain = urlparse(site_url).netloc or site_url
    idx = int(hashlib.sha256(domain.encode()).hexdigest(), 16) % _N_THEMES
    return {key: variants[idx] for key, variants in _VARIANTS.items()}


def apply_class_map_to_file(path: Path, class_map: dict[str, str]) -> bool:
    """Apply class_map replacements to a single file. Returns True if changed."""
    original = path.read_text(encoding="utf-8")
    text = original
    # Longest-first prevents partial matches (ut-sub-sub-list before ut-sub-list)
    for canonical in sorted(class_map, key=len, reverse=True):
        text = text.replace(canonical, class_map[canonical])
    if text == original:
        return False
    path.write_text(text, encoding="utf-8", newline="\n")
    return True


def obfuscate_theme(
    theme_dir: Path,
    site_url: str,
    class_map_path: Path | None = None,
) -> dict[str, str]:
    """
    Reset utheme/ to canonical ut- state, apply per-domain class obfuscation,
    and optionally save the class map to a JSON file for use by other steps.

    Returns the applied class_map.
    """
    result = subprocess.run(
        ["git", "restore", str(theme_dir)],
        cwd=theme_dir.parent,
        capture_output=True,
    )
    if result.returncode != 0:
        # Not a git repo (project copy) — theme_dir is already in canonical state.
        pass

    class_map = make_class_map(site_url)

    if class_map_path is not None:
        import json
        class_map_path.write_text(
            json.dumps(class_map, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    extensions = {".php", ".css", ".scss"}
    changed = 0
    for path in theme_dir.rglob("*"):
        if path.suffix in extensions and path.is_file():
            if apply_class_map_to_file(path, class_map):
                changed += 1

    domain = urlparse(site_url).netloc or site_url
    print(f"  Class obfuscation: {changed} files updated for {domain}")
    return class_map
