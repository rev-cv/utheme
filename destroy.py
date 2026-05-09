#!/usr/bin/env python3
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


def run(cmd: list[str], cwd: Path | None = None) -> int:
    print(f"  $ {' '.join(cmd)}")
    return subprocess.run(cmd, cwd=cwd).returncode


def main():
    project_dir = Path(__file__).parent.resolve()

    # --- Load project .env ---
    env = parse_env(project_dir / ".env")
    if not env:
        sys.exit(f"[ERROR] .env not found or empty: {project_dir / '.env'}")

    container_name = env.get("CONTAINER_NAME", "")
    db_name = env.get("DB_NAME", "")
    db_user = env.get("DB_USER", "")

    if not container_name or not db_name:
        sys.exit("[ERROR] CONTAINER_NAME or DB_NAME missing in .env")

    # --- Locate shared MariaDB and get root password ---
    shared_db_str = env.get("SHARED_DB_PATH", "")
    if shared_db_str:
        shared_db_dir = Path(shared_db_str)
        if not shared_db_dir.is_absolute():
            shared_db_dir = project_dir / shared_db_dir
    else:
        shared_db_dir = next(
            (p.parent / "wp-maria-db" for p in project_dir.parents if p.name == "sites"),
            project_dir.parent / "wp-maria-db",
        )

    db_root_password = extract_yaml_value(
        shared_db_dir / "docker-compose.yml", "MYSQL_ROOT_PASSWORD"
    )

    if not db_root_password:
        db_root_password = input(
            f"  Root password for wp_shared_db (not found in {shared_db_dir}): "
        ).strip()
        if not db_root_password:
            sys.exit("[ERROR] Root password is required.")

    # --- Confirmation ---
    print()
    print("=" * 58)
    print("  DESTROY SITE — will be permanently removed:")
    print(f"  WP container  : {container_name}")
    print(f"  Docker volume : wp_html  (docker compose down -v)")
    print(f"  Database      : {db_name}")
    if db_user:
        print(f"  DB user       : {db_user}")
    print("=" * 58)
    if input("  Type YES to confirm: ").strip() != "YES":
        sys.exit("Aborted.")

    print()

    # --- Step 1: stop all compose services and remove named volumes ---
    print("[1/2] Stopping containers and removing volumes...")
    # --profile dev ensures sass is stopped even if running in dev profile
    code = run(
        ["docker", "compose", "--profile", "dev", "down", "-v"],
        cwd=project_dir,
    )
    if code != 0:
        print(f"  [WARN] docker compose exited with code {code} (may be already stopped)")

    # --- Step 2: drop DB and user from shared MariaDB ---
    print("\n[2/2] Dropping database from wp_shared_db...")
    sql = f"DROP DATABASE IF EXISTS `{db_name}`;"
    if db_user:
        sql += f" DROP USER IF EXISTS '{db_user}'@'%';"

    code = run([
        "docker", "exec", "wp_shared_db",
        "mariadb", f"-uroot", f"-p{db_root_password}",
        "-e", sql,
    ])
    if code != 0:
        print(f"  [WARN] mariadb command exited with code {code}")
    else:
        print("  [OK] Database dropped.")

    # --- Step 3: remove local uploads/ folder ---
    uploads_dir = project_dir / "uploads"
    if uploads_dir.exists():
        print("\n[3/3] Removing uploads/ folder...")
        shutil.rmtree(uploads_dir)
        print("  [OK] uploads/ removed.")
    else:
        print("\n[3/3] uploads/ not found, skipping.")

    # --- Step 4: remove generated wp-config.php ---
    wp_config = project_dir / "wp-conf" / "wp-config.php"
    if wp_config.exists():
        print("\n[4/4] Removing wp-conf/wp-config.php...")
        wp_config.unlink()
        print("  [OK] wp-config.php removed.")
    else:
        print("\n[4/4] wp-conf/wp-config.php not found, skipping.")

    print("\nDone. Site destroyed.")


if __name__ == "__main__":
    main()
