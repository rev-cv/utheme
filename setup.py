# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "python-dotenv"
# ]
# ///

import subprocess
import time
import sys
import os
import shutil
import json
import re
from pathlib import Path
from dotenv import load_dotenv
import socket

load_dotenv(interpolate=True)

# --- Константы ---
DOCKER_COMPOSE_COMMAND = ["docker", "compose"]
WORDPRESS_SERVICE = "wordpress"

# Список папок, которые должны быть в проекте для маппинга
REQUIRED_DIRS = [
    "uploads",
    "plugins",
]

def run_command(command, error_message, check_output=False):
    try:
        print(f"-> Выполнение: {' '.join(command)}")
        process = subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, encoding="utf-8")

        output = []
        while True:
            line = process.stdout.readline()
            if line:
                print(line.strip())
                output.append(line)
            if not line and process.poll() is not None:
                break
        
        process.wait()

        if process.returncode != 0:
            print(f"\n ОШИБКА: {error_message}")
            sys.exit(1)
            
        return "".join(output) if check_output else None

    except Exception as e:
        print(f"\nОШИБКА: {e}")
        sys.exit(1)

def start_sass_watch():
    print("\n=== 1.5/3: Запуск SASS Watch (в новом окне) ===")
    theme_src_path = Path.cwd() / "utheme" / "src"
    
    if not theme_src_path.exists():
        print(f"Папка {theme_src_path} не найдена. Пропуск запуска SASS.")
        return

    sass_cmd = "sass style.scss:style.css --style=compressed --watch --no-source-map"
    
    print(f"Запускаю SASS в папке: {theme_src_path}")
    
    if sys.platform.startswith('win'):
        subprocess.Popen(f'start "SASS Watch" /D "{theme_src_path}" cmd /k "{sass_cmd}"', shell=True)
    else:
        print(f"Запуск в новом окне не поддерживается. Выполните вручную: cd {theme_src_path} && {sass_cmd}")

def start_docker():
    print("\n=== 2/3: Запуск Docker и настройка прав ===")
    
    # 1. Попытка запуска контейнеров с перехватом ошибок порта
    try:
        # Используем Popen, чтобы "прочитать" ошибку до того, как скрипт упадет
        process = subprocess.Popen(
            DOCKER_COMPOSE_COMMAND + ["up", "-d"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        _, stderr = process.communicate()

        if process.returncode != 0:
            if "port is already allocated" in stderr or "Bind for" in stderr:
                print("\n" + "!"*50)
                print("ОШИБКА: HOST_PORT уже занят другим процессом. Освободи порт!")
                print("!"*50 + "\n")
                sys.exit(1) # Жестко выходим, так как дальше идти нет смысла
            else:
                # Если ошибка другая, вызываем стандартную обработку (если она у тебя так работает)
                print(f"Критическая ошибка Docker:\n{stderr}")
                sys.exit(1)
        
        print("   -> Контейнеры успешно запущены.")

    except Exception as e:
        print(f"Ошибка при попытке запуска Docker: {e}")
        sys.exit(1)
    
    # Ожидание старта
    print("   -> Ожидание старта контейнеров (10 сек)...")
    time.sleep(10)

    # 2. Исправление прав
    fix_perm_cmd = "chown -R www-data:www-data /var/www/html/wp-content"
    run_command(
        DOCKER_COMPOSE_COMMAND + ["exec", "-u", "root", WORDPRESS_SERVICE, "bash", "-c", fix_perm_cmd],
        "Не удалось изменить права доступа."
    )

    # 3. Установка WP-CLI
    print("   -> Установка WP-CLI...")
    wp_cli_install_cmd = (
        "curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && "
        "chmod +x wp-cli.phar && "
        "mv wp-cli.phar /usr/local/bin/wp"
    )
    run_command(
        DOCKER_COMPOSE_COMMAND + ["exec", "-u", "root", WORDPRESS_SERVICE, "bash", "-c", wp_cli_install_cmd],
        "Не удалось установить WP-CLI."
    )

def process_temp_credentials():
    """
    Считывает учетные данные из temp_wp.json, обновляет .env и access.txt,
    а затем удаляет временный файл.
    """
    print("\n-> Обработка временных учетных данных из temp_wp.json...")
    
    current_path = Path.cwd()
    json_path = current_path / "uploads" / "temp_wp.json"
    
    if not json_path.exists():
        print(f"Файл {json_path} не найден. Пропуск обновления учетных данных.")
        return

    try:
        # 1. Считывание данных
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)

        admin_user = data.get("admin_user")
        admin_pass = data.get("admin_pass")
        admin_email = data.get("admin_email")
        app_pass = data.get("app_pass")

        if not all([admin_user, admin_pass, admin_email, app_pass]):
            print("В temp_wp.json отсутствуют необходимые поля. Пропуск.")
            return
        
        print("Данные из temp_wp.json успешно считаны.")

        # 2. Запись app_pass в .env
        env_path = current_path / ".env"
        if env_path.exists():
            try:
                content = env_path.read_text(encoding='utf-8')
                new_content = re.sub(
                    r'^(WP_APP_PASSWORD\s*=\s*).*$', 
                    f'WP_APP_PASSWORD="{app_pass}"', 
                    content, 
                    flags=re.MULTILINE
                )
                if new_content != content:
                    env_path.write_text(new_content, encoding='utf-8')
                    print(f"WP_APP_PASSWORD в {env_path.name} обновлен.")
                else:
                    print(f"WP_APP_PASSWORD не найден в {env_path.name} для обновления.")
            except Exception as e:
                print(f"Ошибка при обновлении {env_path.name}: {e}")

        # 3. Сохранение доступов в *_access.txt
        access_files = list(current_path.glob("*_access.txt"))
        if access_files:
            access_file_path = access_files[0]
        else:
            access_file_path = current_path / f"{current_path.name}_access.txt"

        with open(access_file_path, 'a', encoding='utf-8') as f:
            f.write("\n\nПользователь CMS GENERATE:\n")
            f.write(f" - login: {admin_user}\n")
            f.write(f" - password: {admin_pass}\n")
            f.write(f" - email: {admin_email}\n")
        print(f"Доступы CMS GENERATE добавлены в {access_file_path.name}.")

    except Exception as e:
        print(f"Непредвиденная ошибка при обработке учетных данных: {e}")
    finally:
        # Удаляем файл в любом случае
        os.remove(json_path)
        print(f"Временный файл {json_path.name} удален.")

def run_setup_script():
    print("\n=== 3/3: Настройка сайта (setup_site.sh) ===")
    
    # сбор переменных из .env для передачи внутрь контейнера
    env_vars_to_pass = [
        "ADMIN_USER", "ADMIN_EMAIL", "SITE_LANG", 
        "SITE_TITLE", "SITE_URL", "THEME_SLUG"
    ]
    
    exec_cmd = ["exec", "-u", "www-data"]
    for var in env_vars_to_pass:
        val = os.getenv(var)
        if val:
            exec_cmd.extend(["-e", f"{var}={val}"])

    run_command(
        DOCKER_COMPOSE_COMMAND + exec_cmd + [WORDPRESS_SERVICE, "bash", "setup_site.sh"],
        "Скрипт setup_site.sh завершился с ошибкой."
    )

    # обработка учетных данных после выполнения скрипта
    process_temp_credentials()


def is_port_in_use(port: int) -> bool:
    """Проверяет, занят ли TCP порт на localhost."""
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        return s.connect_ex(('localhost', port)) == 0

def find_free_port_and_update_env():
    """
    Проверяет HOST_PORT из .env. Если занят — ищет свободный 
    и обновляет .env файл.
    """
    print("\n=== 0/3: Проверка доступности порта ===")
    
    host_port_str = os.getenv("HOST_PORT")
    if not host_port_str:
        print("HOST_PORT не найден в .env. Используем стандартный анализ Docker.")
        return

    try:
        current_port = int(host_port_str)
    except ValueError:
        print(f"Ошибка: HOST_PORT '{host_port_str}' не является числом.")
        return

    original_port = current_port
    
    # рекурсивный поиск свободного порта
    while is_port_in_use(current_port):
        print(f"   [!] Порт {current_port} занят. Пробую {current_port + 1}...")
        current_port += 1

    if current_port != original_port:
        print(f"   -> Найден свободный порт: {current_port}. Обновляю .env...")
        
        env_path = Path.cwd() / ".env"
        if env_path.exists():
            content = env_path.read_text(encoding='utf-8')
            # замена значения HOST_PORT
            new_content = re.sub(
                r'^(HOST_PORT\s*=\s*).*$', 
                f'HOST_PORT={current_port}', 
                content, 
                flags=re.MULTILINE
            )
            env_path.write_text(new_content, encoding='utf-8')
            
            # обновляем переменные окружения в текущем процессе 
            # чтобы os.getenv("SITE_URL") выдал верный результат позже
            os.environ["HOST_PORT"] = str(current_port)
            # перезагружаем dotenv, чтобы интерполяция SITE_URL=...${HOST_PORT} сработала
            load_dotenv(interpolate=True, override=True) 
        else:
            print("Ошибка: Файл .env не найден для записи порта.")
    else:
        print(f"   -> Порт {current_port} свободен.")

def main():
    print("=====================================================")
    print("Настройка Docker + WordPress")
    print("=====================================================")

    # проверка свободного порта ПЕРЕД запуском Docker
    # если занято, то +1
    find_free_port_and_update_env()

    start_docker()
    run_setup_script()

    start_sass_watch()

    print("\n=====================================================")
    print("УСПЕШНОЕ ЗАВЕРШЕНИЕ!")
    print("=====================================================")
    
    if sys.platform.startswith('win'):
        os.system("pause")
    else:
        input("Нажмите Enter, чтобы завершить...")

if __name__ == "__main__":
    main()