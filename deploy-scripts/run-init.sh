#!/bin/bash
# Использование: ./run-init.sh [TASK_ID ...]
#
# Без аргументов  — только установка окружения + запуск WordPress для уже существующих сайтов
# С аргументами   — дополнительно создаёт папки сайтов из задач Asana перед запуском

# Проверяем, НЕ запущен ли скрипт от root (защита от случайного sudo)
if [ "$EUID" -eq 0 ]; then
  echo "❌ Ошибка: Не запускайте этот скрипт через sudo. Он сам запросит пароль, где нужно."
  exit 1
fi

BASE_DIR=$(pwd)
SITES_DIR="$BASE_DIR/sites"
TASK_IDS=$@

# ======================================================
# Определяем режим запуска
# ======================================================

SKIP_SETUP=false

if [ -d "$BASE_DIR/nginx-proxy-manager" ]; then
    if [ -n "$TASK_IDS" ]; then
        # Передан ID задачи — пропускаем установку и настройку, идём сразу к задаче
        echo "--- Окружение уже настроено. Пропускаю установку, перехожу к выполнению задачи ---"
        SKIP_SETUP=true
    else
        # Повторный запуск без аргументов — спрашиваем пользователя
        echo "⚠️  Скрипт уже запускался ранее (найдена папка nginx-proxy-manager)."
        read -r -p "Продолжить выполнение? [y/N]: " _confirm
        case "$_confirm" in
            [yY][eE][sS]|[yY]) ;;
            *) echo "Отменено."; exit 0 ;;
        esac
    fi
fi

# ======================================================
# ЧАСТЬ 1: Установка системных зависимостей
# ======================================================

# uv — добавляем пути всегда, до любых вызовов uv
export PATH="$HOME/.local/bin:$HOME/.cargo/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

if ! $SKIP_SETUP; then

# zip
if ! command -v zip >/dev/null 2>&1; then
    echo "--- Устанавливаю zip ---"
    sudo apt update && sudo apt install -y zip
fi

# Docker
if ! command -v docker >/dev/null 2>&1; then
    echo "--- Устанавливаю Docker ---"
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker $USER
else
    echo "--- Docker уже установлен ---"
fi

# Активируем группу docker для текущей сессии
# Используем id -Gn, так как она надежнее внутри скриптов при изменении прав на лету
if ! id -Gn | grep -q "\bdocker\b"; then
    echo "--- Активирую права группы Docker для текущей сессии ---"
    # Перезапускаем скрипт с правами группы docker
    exec sg docker -c "$(printf '%q ' "$BASH_SOURCE" "$@")"
fi

if ! command -v uv >/dev/null 2>&1; then
    echo "--- Устанавливаю uv ---"
    curl -LsSf https://astral.sh/uv/install.sh | sh
    [ -f "$HOME/.cargo/env" ] && source "$HOME/.cargo/env"
else
    echo "--- uv уже установлен ---"
fi

# ======================================================
# ЧАСТЬ 2: Подготовка структуры проекта
# ======================================================

echo "--- Рабочая директория: $BASE_DIR ---"
mkdir -p "$SITES_DIR" "$BASE_DIR/nginx-proxy-manager"

# docker-compose.yml для Nginx Proxy Manager
echo "--- Конфигурирую Nginx Proxy Manager ---"
cat <<EOF > "$BASE_DIR/nginx-proxy-manager/docker-compose.yml"
services:
  npm:
    image: 'jc21/nginx-proxy-manager:latest'
    restart: unless-stopped
    ports:
      - '80:80'
      - '81:81'
      - '443:443'
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
    networks:
      default:
        priority: 1000
      web_network:
        priority: 500

networks:
  default:
  web_network:
    external: true
EOF

# Общая сеть Docker
echo "--- Проверяю сеть docker ---"
docker network create web_network  2>/dev/null || echo "Сеть уже существует"

# Запуск Nginx Proxy Manager
echo "--- Запускаю Nginx Proxy Manager ---"
cd "$BASE_DIR/nginx-proxy-manager"
docker compose up -d
cd "$BASE_DIR"

# Скрипт asana.py
echo "--- Создаю скрипт asana.py ---"
cat <<'EOF' > "$SITES_DIR/asana.py"
# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "python-dotenv",
#     "requests",
# ]
# ///
import os
import sys
import requests
import zipfile
import shutil
import io

TOKEN = ''
HEADERS = {"Authorization": f"Bearer {TOKEN}"}
THEME_REPO_URL = "https://github.com/rev-cv/utheme/archive/refs/heads/main.zip"

def get_task_data(task_gid):
    url = f"https://app.asana.com/api/1.1/tasks/{task_gid}?opt_fields=name,notes,custom_fields"
    response = requests.get(url, headers=HEADERS)
    response.raise_for_status()
    return response.json().get('data', {})

def get_latest_zip_info(task_gid):
    url = f"https://app.asana.com/api/1.1/tasks/{task_gid}/attachments?opt_fields=name,created_at,download_url"
    response = requests.get(url, headers=HEADERS)
    response.raise_for_status()
    attach_data = response.json().get('data', [])
    zip_files = [a for a in attach_data if a.get('name', '').lower().endswith('.zip')]
    if not zip_files: return None
    zip_files.sort(key=lambda x: x['created_at'])
    return zip_files[-1]

def download_and_extract_theme(target_dir):
    print(f"Загрузка темы...")
    r = requests.get(THEME_REPO_URL)
    r.raise_for_status()
    with zipfile.ZipFile(io.BytesIO(r.content)) as zip_ref:
        top_folder = zip_ref.namelist()[0].split('/')[0]
        zip_ref.extractall(target_dir)
        source_dir = os.path.join(target_dir, top_folder)
        for item in os.listdir(source_dir):
            shutil.move(os.path.join(source_dir, item), os.path.join(target_dir, item))
        os.rmdir(source_dir)

def format_site_title(title):
    """Первая буква заглавная для слов длиннее 3 символов."""
    return " ".join([w.capitalize() if len(w) > 3 else w for w in title.split()])

def get_custom_field(task_data, field_name):
    for field in task_data.get('custom_fields', []):
        if field.get('name') == field_name:
            return field.get('display_value') or field.get('text_value')
    return None

def setup_environment(task_data, zip_path):
    domain = get_custom_field(task_data, "Домен")
    geo = get_custom_field(task_data, "ГЕО") or "EN"
    if not domain:
        print(f"❌ Ошибка: Нет домена в задаче {task_data.get('name')}")
        return

    script_dir = os.path.dirname(os.path.abspath(__file__))
    base_dir = os.path.join(script_dir, domain)

    if os.path.exists(base_dir):
        print(f"--- Папка {domain} уже существует, пропускаю ---")
        return

    os.makedirs(os.path.join(base_dir, "spec"), exist_ok=True)
    download_and_extract_theme(base_dir)

    with zipfile.ZipFile(zip_path, 'r') as zip_ref:
        zip_ref.extractall(os.path.join(base_dir, "spec"))
    os.remove(zip_path)

    # Очистка вложенности в spec
    spec_dir = os.path.join(base_dir, "spec")
    entries = os.listdir(spec_dir)
    if len(entries) == 1 and os.path.isdir(os.path.join(spec_dir, entries[0])):
        single = os.path.join(spec_dir, entries[0])
        for item in os.listdir(single):
            shutil.move(os.path.join(single, item), os.path.join(spec_dir, item))
        os.rmdir(single)

    task_name = task_data.get('name', 'Site Title')
    site_url = f"http://{domain}"
    container_name = domain.replace('.', '-')
    admin_user = "admin" + "".join(word.capitalize() for word in task_name.split())

    env_content = f"""HOST_PORT=8081
SITE_URL={site_url}

THEME_SLUG="utheme"
SITE_TITLE="{format_site_title(task_name)}"
CONTAINER_NAME="{container_name}"
SITE_LANG="{geo}"
# EN, FR, DE, PL, CZ, PT, IT, NL, ES, SK, ET...
ADMIN_USER="{admin_user}"
ADMIN_EMAIL="admin@{domain}"

WP_APP_PASSWORD=""

DB_NAME=""
DB_USER=""
DB_PASSWORD=""

WP_CPU_LIMIT=0.5
WP_MEM_LIMIT=256m

SCHEDULE_PATTERN="3d 2-3p (10-21)"
"""
    with open(os.path.join(base_dir, ".env"), "w", encoding="utf-8") as f:
        f.write(env_content)

    print(f"✅ Готово: {domain}")

def main():
    task_gids = sys.argv[1:]
    if not task_gids: return
    for gid in task_gids:
        try:
            task = get_task_data(gid)
            zip_info = get_latest_zip_info(gid)
            if not zip_info: continue
            local_zip = f"temp_{gid}.zip"
            with requests.get(zip_info['download_url'], stream=True) as r:
                r.raise_for_status()
                with open(local_zip, 'wb') as f:
                    for chunk in r.iter_content(chunk_size=8192): f.write(chunk)
            setup_environment(task, os.path.abspath(local_zip))
        except Exception as e:
            print(f"❌ Ошибка в {gid}: {e}")

if __name__ == "__main__":
    main()
EOF

fi # конец блока if ! $SKIP_SETUP

# ======================================================
# ЧАСТИ 3 + 4: Создание сайтов из Asana и установка WordPress
# ======================================================

_add_to_hosts() {
    local domain="$1"
    local entry="127.0.0.1  $domain"
    if grep -qF "$domain" /etc/hosts 2>/dev/null; then
        echo "--- /etc/hosts: запись для $domain уже существует, пропускаю ---"
    else
        echo "--- Добавляю $entry в /etc/hosts ---"
        echo "$entry" | sudo tee -a /etc/hosts > /dev/null
        echo "✅ /etc/hosts обновлён: $entry"
    fi
}

_run_setup_for_domain() {
    local domain="$1"
    local site_path="$SITES_DIR/$domain"
    echo "🚀 Захожу в папку: $domain"
    cd "$site_path" || return
    if [ -f "setup.py" ]; then
        echo "--- Запускаю uv run setup.py для $domain ---"
        uv run setup.py
    else
        echo "⚠️  Пропуск: setup.py не найден в папке $domain"
    fi
    cd "$BASE_DIR"
    echo "--- Завершено для $domain ---"
    echo "------------------------------------"
}

if [ -n "$TASK_IDS" ]; then
    echo "--- Обнаружены ID задач, создаю папки сайтов из Asana ---"
    if ! command -v uv >/dev/null 2>&1; then
        echo "❌ Ошибка: uv не найден в PATH."
        exit 1
    fi

    # Запоминаем папки, существующие до запуска asana.py
    declare -A _before_map
    for _d in "$SITES_DIR"/*/; do
        [ -d "$_d" ] && _before_map["$(basename "$_d")"]=1
    done

    uv run "$SITES_DIR/asana.py" $TASK_IDS

    # Определяем только новые папки (без grep по кириллице)
    NEW_DOMAINS=""
    for _d in "$SITES_DIR"/*/; do
        if [ -d "$_d" ]; then
            _domain="$(basename "$_d")"
            [ -z "${_before_map[$_domain]+x}" ] && NEW_DOMAINS="$NEW_DOMAINS $_domain"
        fi
    done
    NEW_DOMAINS="${NEW_DOMAINS# }"

    if [ -z "$NEW_DOMAINS" ]; then
        echo "--- Новых сайтов не создано (возможно, уже существуют). Пропускаю установку. ---"
    else
        echo "--- Запускаю установку для новых сайтов ---"
        for domain in $NEW_DOMAINS; do
            _add_to_hosts "$domain"
            _run_setup_for_domain "$domain"
        done
    fi

else
    echo "--- ID задач не переданы, обрабатываю все существующие сайты ---"

    if [ ! -d "$SITES_DIR" ] || [ -z "$(ls -A "$SITES_DIR" 2>/dev/null | grep -v "asana.py")" ]; then
        echo "--- В папке $SITES_DIR нет проектов для обработки. Завершаю работу. ---"
        exit 0
    fi

    echo "--- Начинаю установку сайтов по очереди ---"
    for site_path in "$SITES_DIR"/* ; do
        [ -d "$site_path" ] && _run_setup_for_domain "$(basename "$site_path")"
    done
fi

# ======================================================
# ЧАСТЬ 5: Сбор файлов доступа в архив
# ======================================================

echo "--- Все установки завершены. Собираю доступы ---"

ZIP_NAME="accesses.zip"
TEMP_COLLECT="temp_accesses_$(date +%s)"
mkdir -p "$TEMP_COLLECT"

find "$SITES_DIR" -type f -name "*_access.txt" -exec cp {} "$TEMP_COLLECT/" \;

if [ "$(ls -A "$TEMP_COLLECT")" ]; then
    zip -ju "$ZIP_NAME" "$TEMP_COLLECT"/*
    echo "✅ Архив $ZIP_NAME обновлён (добавлено/обновлено $(ls "$TEMP_COLLECT" | wc -l) файлов)."
else
    echo "⚠️  Файлы доступа *_access.txt не найдены."
fi

rm -rf "$TEMP_COLLECT"
echo "--- Работа завершена! ---"
