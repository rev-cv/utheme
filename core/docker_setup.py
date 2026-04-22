import os
import re
import secrets
import socket
import subprocess
import sys
import time
from pathlib import Path

from dotenv import load_dotenv

COMPOSE      = ["docker", "compose"]
WP_SERVICE   = "wordpress"
DB_CONTAINER = "wp_shared_db"
DB_NETWORK   = "shared_db_network"
WEB_NETWORK  = "web_network"


# ─── Публичный API ───────────────────────────────────────────────────────────

def run(manifest: dict, staging_dir: Path, wp_conf_dir: Path) -> None:
    container = os.getenv("CONTAINER_NAME", "wp_site")

    _clean_wp_config_volume(wp_conf_dir)
    _ensure_shared_db()
    _create_site_db()
    _start_container(container)
    _copy_staging(staging_dir, container)
    _copy_provision_sh(wp_conf_dir, container)
    _run_provision_sh(container)
    _process_credentials(container)
    _cleanup_tmp(container, staging_dir.name)
    _extract_wp_config(container, wp_conf_dir)

    # print("  Рандомизация темы...")
    # from .randomize_theme import randomize_theme
    # randomize_theme()

    print("\n  Деплой завершён.")


# ─── Шаги ────────────────────────────────────────────────────────────────────

def _find_free_port() -> None:
    print("  Проверка порта...")
    raw = os.getenv("HOST_PORT", "8081")
    try:
        port = int(raw)
    except ValueError:
        port = 8081

    original = port
    while _port_busy(port):
        port += 1

    env_path = Path(".env")
    content  = env_path.read_text(encoding="utf-8") if env_path.exists() else ""

    def _set(key, val):
        nonlocal content
        if re.search(rf"^{key}\s*=", content, re.MULTILINE):
            content = re.sub(rf"^({key}\s*=\s*).*$", f"{key}={val}", content, flags=re.MULTILINE)
        else:
            content += f"\n{key}={val}"

    _set("HOST_PORT", port)
    _set("SITE_URL",  f"http://localhost:{port}")
    env_path.write_text(content, encoding="utf-8", newline="\n")

    os.environ["HOST_PORT"] = str(port)
    os.environ["SITE_URL"]  = f"http://localhost:{port}"
    load_dotenv(override=True)

    if port != original:
        print(f"  Порт изменён: {original} → {port}")
    else:
        print(f"  Порт {port} свободен.")


def _clean_wp_config_volume(wp_conf_dir: Path) -> None:
    if not (wp_conf_dir / "wp-config.php").exists():
        _remove_volume_from_compose("/var/www/html/wp-config.php")


def _ensure_shared_db() -> None:
    print("  Проверка shared MariaDB...")
    _ensure_network(DB_NETWORK)

    result = subprocess.run(
        ["docker", "inspect", "--format", "{{.State.Running}}", DB_CONTAINER],
        capture_output=True, text=True,
    )
    if result.returncode == 0 and result.stdout.strip() == "true":
        print(f"  {DB_CONTAINER} уже запущен.")
        return

    shared_db_dir = _shared_db_dir()
    shared_db_dir.mkdir(parents=True, exist_ok=True)

    env_path = shared_db_dir / ".env"
    root_password = _read_db_root_password(shared_db_dir)
    if not root_password:
        root_password = secrets.token_hex(16)
        env_path.write_text(f"DB_ROOT_PASSWORD={root_password}\n", encoding="utf-8", newline="\n")

    compose_path    = shared_db_dir / "docker-compose.yml"
    compose_content = _shared_db_compose(root_password)
    if not compose_path.exists() or compose_path.read_text(encoding="utf-8") != compose_content:
        compose_path.write_text(compose_content, encoding="utf-8", newline="\n")

    _run(["docker", "compose", "--project-directory", str(shared_db_dir), "up", "-d"])

    print("  Ожидание готовности БД...")
    for _ in range(30):
        r = subprocess.run(
            ["docker", "inspect", "--format", "{{.State.Health.Status}}", DB_CONTAINER],
            capture_output=True, text=True,
        )
        if r.stdout.strip() == "healthy":
            print("  БД готова.")
            return
        time.sleep(3)
    print("  [!] БД не стала healthy за 90 сек, продолжаем...")


def _create_site_db() -> None:
    print("  Создание БД для сайта...")
    container_name = os.getenv("CONTAINER_NAME", "wp_site")
    ident = re.sub(r"[^a-zA-Z0-9_]", "_", container_name)

    db_name = os.getenv("DB_NAME") or ident
    db_user = os.getenv("DB_USER") or ident
    db_pass = os.getenv("DB_PASSWORD") or secrets.token_urlsafe(16)

    sql = (
        f"CREATE DATABASE IF NOT EXISTS `{db_name}` "
        f"CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; "
        f"CREATE USER IF NOT EXISTS '{db_user}'@'%' IDENTIFIED BY '{db_pass}'; "
        f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'%'; "
        f"FLUSH PRIVILEGES;"
    )
    root_pass = _read_db_root_password(_shared_db_dir())
    if not root_pass:
        print("  [!] Не найден root-пароль MariaDB.")
        sys.exit(1)

    result = subprocess.run(
        ["docker", "exec", DB_CONTAINER,
         "mysql", "-h", "127.0.0.1", "-u", "root", f"-p{root_pass}", "-e", sql],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print(f"  [!] Ошибка создания БД: {result.stderr}")
        sys.exit(1)

    env_path = Path(".env")
    content  = env_path.read_text(encoding="utf-8") if env_path.exists() else ""
    for key, val in [("DB_NAME", db_name), ("DB_USER", db_user), ("DB_PASSWORD", db_pass)]:
        if re.search(rf"^{key}\s*=", content, re.MULTILINE):
            content = re.sub(rf"^({key}\s*=\s*).*$", f"{key}={val}", content, flags=re.MULTILINE)
        else:
            content += f"\n{key}={val}"
    env_path.write_text(content, encoding="utf-8", newline="\n")
    os.environ.update({"DB_NAME": db_name, "DB_USER": db_user, "DB_PASSWORD": db_pass})
    load_dotenv(override=True)

    print(f"  БД '{db_name}' и пользователь '{db_user}' готовы.")


def _start_container(container: str) -> None:
    print("  Запуск контейнера WordPress...")
    _ensure_network(WEB_NETWORK)

    result = subprocess.run(
        COMPOSE + ["up", "-d", WP_SERVICE],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        err = result.stderr
        if "port is already allocated" in err or "Bind for" in err:
            print("  [!] PORT уже занят. Освободи порт и перезапусти.")
        else:
            print(f"  [!] Docker error:\n{err}")
        sys.exit(1)

    print("  Ожидание готовности WordPress...")
    for _ in range(30):
        r = subprocess.run(
            ["docker", "inspect", "--format", "{{.State.Health.Status}}", container],
            capture_output=True, text=True,
        )
        if r.stdout.strip() == "healthy":
            print("  WordPress готов.")
            break
        time.sleep(5)
    else:
        print("  [!] WP не стал healthy за 150 сек, продолжаем...")

    # Права на wp-content + обязательные директории
    _exec_root(container,
        "mkdir -p /var/www/html/wp-content/upgrade /var/www/html/wp-content/cache && "
        "chown -R www-data:www-data /var/www/html/wp-content && "
        "chmod -R 775 /var/www/html/wp-content"
    )

    # На Windows WSL2 chown на NTFS bind mounts не работает — www-data не становится
    # реальным владельцем plugins/ и uploads/. Применяем 777 только там и только на Windows.
    import platform
    if platform.system() == "Windows":
        _exec_root(container,
            "chmod -R 777 /var/www/html/wp-content/plugins "
            "/var/www/html/wp-content/uploads "
            "/var/www/html/wp-content/upgrade"
        )

    # WP-CLI
    print("  Установка WP-CLI...")
    _exec_root(container,
        "curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && "
        "chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"
    )
    print("  WP-CLI установлен.")


def _copy_staging(staging_dir: Path, container: str) -> None:
    print(f"  Копирование {staging_dir.name}/ → /tmp/ в контейнере...")
    result = subprocess.run(
        ["docker", "cp", str(staging_dir), f"{container}:/tmp/"],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print(f"  [!] docker cp failed: {result.stderr}")
        sys.exit(1)
    print(f"  Скопировано в /tmp/{staging_dir.name}/")


def _copy_provision_sh(wp_conf_dir: Path, container: str) -> None:
    src = wp_conf_dir / "provision.sh"
    print(f"  Копирование provision.sh → /tmp/ в контейнере...")
    result = subprocess.run(
        ["docker", "cp", str(src), f"{container}:/tmp/provision.sh"],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print(f"  [!] docker cp provision.sh failed: {result.stderr}")
        sys.exit(1)


def _run_provision_sh(container: str) -> None:
    print("  Запуск /tmp/provision.sh внутри контейнера...")
    result = subprocess.run(
        ["docker", "exec", "-u", "root", container, "bash", "/tmp/provision.sh"],
        text=True,
    )
    subprocess.run(
        ["docker", "exec", container, "rm", "-f", "/tmp/provision.sh"],
        capture_output=True,
    )
    if result.returncode != 0:
        print("  [!] provision.sh завершился с ошибкой.")
        sys.exit(1)
    print("  provision.sh выполнен.")


def _process_credentials(container: str) -> None:
    """Читает temp_wp.json из uploads/, пишет в .env и *_access.txt."""
    json_path = Path("uploads") / "temp_wp.json"
    if not json_path.exists():
        print("  temp_wp.json не найден, пропуск.")
        return

    import json
    data = json.loads(json_path.read_text(encoding="utf-8"))
    admin_user  = data.get("admin_user", "")
    admin_pass  = data.get("admin_pass", "")
    admin_email = data.get("admin_email", "")
    app_pass    = data.get("app_pass", "")

    # Записываем app_pass в .env
    env_path = Path(".env")
    content  = env_path.read_text(encoding="utf-8") if env_path.exists() else ""
    if re.search(r"^WP_APP_PASSWORD\s*=", content, re.MULTILINE):
        content = re.sub(r"^(WP_APP_PASSWORD\s*=\s*).*$", f'WP_APP_PASSWORD="{app_pass}"',
                         content, flags=re.MULTILINE)
    else:
        content += f'\nWP_APP_PASSWORD="{app_pass}"'
    env_path.write_text(content, encoding="utf-8", newline="\n")

    # Дописываем в *_access.txt
    cwd = Path.cwd()
    access_files = list(cwd.glob("*_access.txt"))
    access_path  = access_files[0] if access_files else cwd / f"{cwd.name}_access.txt"
    with open(access_path, "a", encoding="utf-8") as f:
        f.write("\n\nWordPress (сгенерировано pipeline):\n")
        f.write(f"  login:    {admin_user}\n")
        f.write(f"  password: {admin_pass}\n")
        f.write(f"  email:    {admin_email}\n")
        f.write(f"  app pass: {app_pass}\n")

    print(f"  Учётные данные записаны в {access_path.name}")

    # Удаляем временный JSON из контейнера
    subprocess.run(
        ["docker", "exec", "-u", "root", os.getenv("CONTAINER_NAME", "wp_site"),
         "rm", "-f", "/var/www/html/wp-content/uploads/temp_wp.json"],
        capture_output=True,
    )


def _cleanup_tmp(container: str, staging_name: str) -> None:
    print(f"  Очистка /tmp/{staging_name}/ из контейнера...")
    subprocess.run(
        ["docker", "exec", container, "rm", "-rf", f"/tmp/{staging_name}"],
        capture_output=True,
    )
    print("  Временные файлы удалены.")


def _extract_wp_config(container: str, wp_conf_dir: Path) -> None:
    config_path = wp_conf_dir / "wp-config.php"
    if config_path.exists():
        _add_volume_to_compose("./wp-conf/wp-config.php:/var/www/html/wp-config.php")
        return

    print("  Извлечение wp-config.php из контейнера...")
    config_path.parent.mkdir(parents=True, exist_ok=True)
    result = subprocess.run(
        ["docker", "cp", f"{container}:/var/www/html/wp-config.php", str(config_path)],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print(f"  [!] Не удалось извлечь wp-config.php: {result.stderr}")
        return

    added = _add_volume_to_compose("./wp-conf/wp-config.php:/var/www/html/wp-config.php")
    if added:
        print("  Пересоздание контейнера с wp-config.php volume...")
        subprocess.run(COMPOSE + ["up", "-d", WP_SERVICE], capture_output=True)
    print(f"  wp-config.php сохранён в wp-conf/")


# ─── Вспомогательные функции ─────────────────────────────────────────────────

def _port_busy(port: int) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        return s.connect_ex(("localhost", port)) == 0


def _ensure_network(name: str) -> None:
    r = subprocess.run(["docker", "network", "inspect", name], capture_output=True)
    if r.returncode != 0:
        subprocess.run(["docker", "network", "create", name], check=True, capture_output=True)


def _exec_root(container: str, cmd: str) -> None:
    result = subprocess.run(
        COMPOSE + ["exec", "-u", "root", WP_SERVICE, "bash", "-c", cmd],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print(f"  [!] exec_root failed: {result.stderr.strip()}")


def _run(cmd: list) -> None:
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(f"  [!] Команда завершилась с ошибкой:\n{result.stderr}")
        sys.exit(1)


def _shared_db_dir() -> Path:
    current = Path(__file__).resolve().parent.parent
    for parent in current.parents:
        if parent.name == "sites":
            return parent.parent / "wp-maria-db"
    return current.parent / "wp-maria-db"


def _read_db_root_password(shared_db_dir: Path) -> str | None:
    env_path = shared_db_dir / ".env"
    if not env_path.exists():
        return None
    m = re.search(r"^DB_ROOT_PASSWORD=([^\s\r\n]+)", env_path.read_text(encoding="utf-8"), re.MULTILINE)
    return m.group(1).strip().strip("\"'") if m else None


def _shared_db_compose(password: str) -> str:
    return f"""\
services:
  db:
    image: mariadb:10.6
    container_name: wp_shared_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: "{password}"
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - shared_db_network
    deploy:
      resources:
        limits:
          cpus: "2"
          memory: 1g
    healthcheck:
      test: ["CMD", "mysql", "-u", "root", "-p{password}", "-e", "SELECT 1"]
      interval: 5s
      timeout: 3s
      retries: 10
      start_period: 20s

volumes:
  db_data: {{}}

networks:
  shared_db_network:
    external: true
"""


def _add_volume_to_compose(volume_line: str) -> bool:
    dc_path = Path("docker-compose.yml")
    content = dc_path.read_text(encoding="utf-8")
    dest = volume_line.split(":")[-1].strip()
    if dest in content:
        return False

    lines = content.split("\n")
    new_lines, in_wp, in_vol, inserted = [], False, False, False
    for i, line in enumerate(lines):
        new_lines.append(line)
        if line.strip().startswith("wordpress:"):
            in_wp = True
        if in_wp and re.match(r"^  \S", line) and not line.strip().startswith("wordpress"):
            in_wp = in_vol = False
        if in_wp and line.strip() == "volumes:":
            in_vol = True
        if in_wp and in_vol and not inserted:
            next_line = lines[i + 1] if i + 1 < len(lines) else ""
            if line.strip().startswith("- ") and not next_line.strip().startswith("- "):
                indent = line[: len(line) - len(line.lstrip())]
                new_lines.append(f"{indent}- {volume_line}")
                inserted = in_vol = True

    if inserted:
        dc_path.write_text("\n".join(new_lines), encoding="utf-8")
    return inserted


def _remove_volume_from_compose(dest_path: str) -> bool:
    dc_path = Path("docker-compose.yml")
    content = dc_path.read_text(encoding="utf-8")
    if dest_path not in content:
        return False
    lines = [l for l in content.split("\n")
             if not (dest_path in l and l.strip().startswith("- "))]
    dc_path.write_text("\n".join(lines), encoding="utf-8")
    return True
