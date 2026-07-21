import re
import random
from pathlib import Path

from core.console import action, result

_SCSS_FILE = Path(__file__).parent.parent / "utheme" / "src" / "conf.scss"

_CONFIG = {
    "main-menu": [
        "island", "aside", "boring",
        "docs", "hierarchical"
    ],
    "footer-menu": [
        "2columns", "central"
    ],
    "cookie-notice": [
        "original", "push-banner", "edge-bar"
    ],
    "toc-menu": [
        "circle", "number", "icon"
    ],
    "is-not-section": [
        "true", "false"
    ],
    "details": [
        "plus", "arrow"
    ],
    "article-card": [
        "default", "frame", "slide", "windows",
        "float", "soft", "split"
    ],
    "image-style": [
        "original", "marginalia", "slide-up", "whisper", "corner-badge", "brutalist-strip"
    ],
    "is-left-align": [
        "true", "false"
    ],
    "is-border": [
        "true", "false"
    ],
    "font-vibe": [
        "google", "strict", "editorial", "startup", "space",
        "syntax", "neo-swiss", "engineer", "vogue", "boutique",
        "wisdom", "noble", "manuscript", "brutal", "urban",
        "manifesto", "black-metal", "raw", "velocity",
        "courtside", "district", "blast", "industry",
        "overdrive", "organic", "vintage", "interface", "antidesign"
    ],
    "density-factor": (0.5, 1.5),
    "seed-hue": (0, 360),
    "$mood-color": [
        "luxury",
        "neon",
        "corporate",
    ],
    "$scheme-type": [
        "luxury",
        "minimalist",
        "vibrant",
        "bold-dark",
        "graphite",
        "pastoral",
        "japane"
    ],
    "font-size": ["16px", "17px", "18px", "19px", "20px", "21px", "22px", "23px", "24px"]
}

_NO_QUOTE_VARS = {"density-factor", "seed-hue", "font-size"}

# cookie-color не рандомизируется независимо — наследуется от того, какой
# cookie-notice выбран этим скриптом (зеркалит handle_randomize() в
# u-theme-styles.php). Пользователь может затем вручную поменять cookie-color
# в админке плагина — эта рандомизация его не трогает.
_COOKIE_COLOR_MAP = {
    "original": "contrast",
    "push-banner": "warning",
    "edge-bar": "section",
}


def randomize_theme() -> None:
    action("Рандомизация темы (conf.scss)")

    if not _SCSS_FILE.exists():
        result(f"conf.scss не найден: {_SCSS_FILE}", style="bold red")
        return

    content = _SCSS_FILE.read_text(encoding="utf-8")
    changes = []

    for var_name, options in _CONFIG.items():
        if not options:
            continue

        if isinstance(options, tuple) and len(options) == 2:
            a, b = options
            new_val = random.randint(a, b) if isinstance(a, int) else round(random.uniform(a, b), 2)
        else:
            new_val = random.choice(options)

        replacement_val = str(new_val) if var_name in _NO_QUOTE_VARS else f'"{new_val}"'
        pattern = re.compile(rf'(\${re.escape(var_name)}:\s*)(.+?)(;)')

        def _replace(match, rv=replacement_val, vn=var_name):
            if match.group(2).strip() != rv:
                changes.append(f"    ${vn}: {match.group(2).strip()} → {rv}")
            return f"{match.group(1)}{rv}{match.group(3)}"

        content = pattern.sub(_replace, content)

        if var_name == "cookie-notice" and new_val in _COOKIE_COLOR_MAP:
            color_val = _COOKIE_COLOR_MAP[new_val]
            color_pattern = re.compile(r'(\$cookie-color:\s*)(.+?)(;)')

            def _replace_color(match, rv=f'"{color_val}"'):
                if match.group(2).strip() != rv:
                    changes.append(f"    $cookie-color: {match.group(2).strip()} → {rv}")
                return f"{match.group(1)}{rv}{match.group(3)}"

            content = color_pattern.sub(_replace_color, content)

    _SCSS_FILE.write_text(content, encoding="utf-8")
    if changes:
        result(f"Тема рандомизирована ({len(changes)} параметров):", style="green")
        for line in changes:
            result(line.strip())
    else:
        result("Случайные значения совпали с текущими, без изменений.")
