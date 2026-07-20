#!/usr/bin/env python3
# /// script
# requires-python = ">=3.13"
# dependencies = ["rich"]
# ///
"""
destroy.py — Full site teardown.

Stops WordPress container (all profiles), removes its named volume,
then drops the database and DB user from the shared MariaDB container.
Works on Linux and Windows.
"""

import re
import shutil
import subprocess
import sys
from pathlib import Path

from rich.live import Live
from rich.panel import Panel
from rich.prompt import Confirm, Prompt
from rich.table import Table
from rich.text import Text

from core.console import (
    ACTION_STYLE, ERROR_STYLE, MUTED_STYLE, RESULT_INDENT, SUCCESS_STYLE,
    console, error, header, phase, result,
)


def parse_env(path: Path) -> dict:
    env = {}
    if not path.exists():
        return env
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if "=" in line:
            key, _, val = line.partition("=")
            env[key.strip()] = val.strip().strip('"').strip("'")
    return env


def extract_yaml_value(path: Path, key: str) -> str | None:
    if not path.exists():
        return None
    text = path.read_text(encoding="utf-8")
    m = re.search(rf'{re.escape(key)}:\s*["\']?([^"\'\n]+)["\']?', text)
    return m.group(1).strip() if m else None


def run(cmd: list[str], cwd: Path | None = None, display_cmd: list[str] | None = None) -> int:
    shown = display_cmd or cmd
    console.print(f"{RESULT_INDENT}$ {' '.join(shown)}", style=MUTED_STYLE)
    proc = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)
    if proc.returncode != 0:
        details = (proc.stderr or proc.stdout).strip()
        if details:
            console.print(f"{RESULT_INDENT}{details}", style=ERROR_STYLE)
    return proc.returncode


def _read_choice_key() -> str:
    """Read one navigation key without requiring Enter."""
    if sys.platform == "win32":
        import msvcrt

        key = msvcrt.getwch()
        if key in ("\x00", "\xe0"):
            return {"H": "up", "P": "down", "K": "left", "M": "right"}.get(msvcrt.getwch(), "")
        return {"\r": "enter", "\x1b": "escape", "\x03": "interrupt"}.get(key, key)

    import termios
    import tty

    fd = sys.stdin.fileno()
    previous = termios.tcgetattr(fd)
    try:
        tty.setraw(fd)
        key = sys.stdin.read(1)
        if key == "\x1b":
            sequence = sys.stdin.read(2)
            return {"[A": "up", "[B": "down", "[C": "right", "[D": "left"}.get(sequence, "escape")
        return {"\r": "enter", "\n": "enter", "\x03": "interrupt"}.get(key, key)
    finally:
        termios.tcsetattr(fd, termios.TCSADRAIN, previous)


def _confirmation_render(selected: bool) -> Text:
    yes_mark, no_mark = ("●", "○") if selected else ("○", "●")
    yes_style = SUCCESS_STYLE if selected else MUTED_STYLE
    no_style = ERROR_STYLE if not selected else MUTED_STYLE
    text = Text("  Permanently destroy this site?\n\n", style="bold")
    text.append(f"    {yes_mark} Yes", style=yes_style)
    text.append("       ")
    text.append(f"{no_mark} No", style=no_style)
    text.append("\n\n  ←/→ or ↑/↓ — select    Enter — confirm    Esc — cancel", style=MUTED_STYLE)
    return text


def confirm_destroy() -> bool:
    """Radio-style confirmation; No is selected by default."""
    if not console.is_terminal or not sys.stdin.isatty():
        return Confirm.ask("  Permanently destroy this site?", default=False, console=console)

    selected = False
    with Live(_confirmation_render(selected), console=console, auto_refresh=False, transient=True) as live:
        while True:
            key = _read_choice_key()
            if key in {"left", "right", "up", "down"}:
                selected = not selected
                live.update(_confirmation_render(selected), refresh=True)
            elif key == "enter":
                return selected
            elif key in {"escape", "interrupt"}:
                return False


def main() -> int:
    project_dir = Path(__file__).parent.resolve()

    header("DESTROY SITE")

    # --- Load project .env ---
    env = parse_env(project_dir / ".env")
    if not env:
        error(f".env not found or empty: {project_dir / '.env'}")
        return 1

    container_name = env.get("CONTAINER_NAME", "")
    db_name = env.get("DB_NAME", "")
    db_user = env.get("DB_USER", "")

    if not container_name or not db_name:
        error("CONTAINER_NAME or DB_NAME missing in .env")
        return 1

    # --- Locate shared MariaDB and get root password ---
    shared_db_str = env.get("SHARED_DB_PATH", "")
    if shared_db_str:
        shared_db_dir = Path(shared_db_str)
        if not shared_db_dir.is_absolute():
            shared_db_dir = project_dir / shared_db_dir
    else:
        shared_db_dir = None
        for parent in project_dir.parents:
            if parent.name == "sites":
                shared_db_dir = parent.parent / "wp-maria-db"
                break
            candidate = parent / "wp-maria-db"
            if candidate.exists():
                shared_db_dir = candidate
                break
        if shared_db_dir is None:
            shared_db_dir = project_dir.parent.parent / "wp-maria-db"

    db_env = parse_env(shared_db_dir / ".env")
    db_root_password = db_env.get("DB_ROOT_PASSWORD") or extract_yaml_value(
        shared_db_dir / "docker-compose.yml", "MYSQL_ROOT_PASSWORD"
    )

    if not db_root_password:
        db_root_password = Prompt.ask(
            f"  Root password for wp_shared_db (not found in {shared_db_dir})",
            password=True,
            console=console,
        ).strip()
        if not db_root_password:
            error("Root password is required.")
            return 1

    # --- Confirmation ---
    table = Table.grid(padding=(0, 2))
    table.add_column(style=ACTION_STYLE)
    table.add_column()
    table.add_row("WP container", container_name)
    table.add_row("Docker volume", "wp_html  (docker compose down -v)")
    table.add_row("Database", db_name)
    if db_user:
        table.add_row("DB user", db_user)
    console.print(Panel(table, title="WILL BE PERMANENTLY REMOVED", border_style=ERROR_STYLE))

    if not confirm_destroy():
        result("Aborted. Nothing was removed.", style="yellow")
        return 0

    result("Destruction confirmed.", style="green")

    # --- Step 1: stop all compose services and remove named volumes ---
    phase(1, 4, "Stopping containers and removing volumes")
    # --profile dev ensures sass is stopped even if running in dev profile
    code = run(
        ["docker", "compose", "--profile", "dev", "down", "-v"],
        cwd=project_dir,
    )
    if code != 0:
        result(f"Docker Compose exited with code {code} (may be already stopped).", style="yellow")
    else:
        result("Containers and volumes removed.", style="green")

    # --- Step 2: drop DB and user from shared MariaDB ---
    phase(2, 4, "Dropping database from wp_shared_db")
    sql = f"DROP DATABASE IF EXISTS `{db_name}`;"
    if db_user:
        sql += f" DROP USER IF EXISTS '{db_user}'@'%';"

    db_cmd = [
        "docker", "exec", "wp_shared_db",
        "mariadb", f"-uroot", f"-p{db_root_password}",
        "-e", sql,
    ]
    safe_db_cmd = ["-p******" if part.startswith("-p") else part for part in db_cmd]
    code = run(db_cmd, display_cmd=safe_db_cmd)
    if code != 0:
        result(f"MariaDB command exited with code {code}.", style="yellow")
    else:
        result("Database and user dropped.", style="green")

    # --- Step 3: remove local uploads/ folder ---
    phase(3, 4, "Removing uploads folder")
    uploads_dir = project_dir / "uploads"
    if uploads_dir.exists():
        shutil.rmtree(uploads_dir)
        result("uploads/ removed.", style="green")
    else:
        result("uploads/ not found, skipping.")

    # --- Step 4: remove generated wp-config.php ---
    phase(4, 4, "Removing generated wp-config.php")
    wp_config = project_dir / "wp-conf" / "wp-config.php"
    if wp_config.exists():
        wp_config.unlink()
        result("wp-conf/wp-config.php removed.", style="green")
    else:
        result("wp-conf/wp-config.php not found, skipping.")

    header("SITE DESTROYED")
    return 0


if __name__ == "__main__":
    sys.exit(main())
