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

def add_volume_to_compose(volume_line):
    """
    Добавляет строку volume в секцию volumes сервиса wordpress в docker-compose.yml.
    Вставляет перед первой строкой, которая НЕ начинается с '      - ' после 'volumes:'.
    Пропускает добавление, если volume уже есть.
    """
    dc_path = Path("docker-compose.yml")
    content = dc_path.read_text(encoding="utf-8")
    
    # Проверяем, есть ли уже такой volume (по ключевой части пути назначения)
    # Извлекаем путь назначения (часть после ':')
    dest_path = volume_line.split(":")[-1].strip()
    if dest_path in content:
        print(f"   -> Volume для {dest_path} уже существует в docker-compose.yml")
        return False
    
    lines = content.split("\n")
    new_lines = []
    in_wordpress = False
    in_volumes = False
    inserted = False
    
    for i, line in enumerate(lines):
        new_lines.append(line)
        
        # Отслеживаем, что мы внутри сервиса wordpress
        if line.strip().startswith("wordpress:"):
            in_wordpress = True
            continue
        
        # Выходим из wordpress при встрече другого сервиса на том же уровне
        if in_wordpress and re.match(r'^  \S', line) and not line.strip().startswith("wordpress"):
            in_wordpress = False
            in_volumes = False
        
        # Нашли секцию volumes внутри wordpress
        if in_wordpress and line.strip() == "volumes:":
            in_volumes = True
            continue
        
        # Внутри volumes — ищем последний элемент списка
        if in_wordpress and in_volumes and not inserted:
            # Текущая строка — элемент volumes (начинается с "      - ")
            # Проверяем, что следующая строка НЕ элемент volumes
            next_line = lines[i + 1] if i + 1 < len(lines) else ""
            if line.strip().startswith("- ") and not next_line.strip().startswith("- "):
                # Определяем отступ из текущей строки
                indent = line[:len(line) - len(line.lstrip())]
                new_lines.append(f"{indent}- {volume_line}")
                inserted = True
                in_volumes = False
    
    if inserted:
        dc_path.write_text("\n".join(new_lines), encoding="utf-8")
        print(f"   -> Volume добавлен в docker-compose.yml: {volume_line}")
        return True
    else:
        print(f"   [!] Не удалось найти место для вставки volume в docker-compose.yml")
        return False


def remove_volume_from_compose(dest_path):
    """
    Удаляет строку volume из docker-compose.yml по пути назначения.
    Например: remove_volume_from_compose("/var/www/html/wp-config.php")
    """
    dc_path = Path("docker-compose.yml")
    content = dc_path.read_text(encoding="utf-8")
    
    if dest_path not in content:
        return False
    
    lines = content.split("\n")
    new_lines = []
    removed = False
    
    for line in lines:
        # Пропускаем строку, которая содержит mount с указанным путём назначения
        if dest_path in line and line.strip().startswith("- "):
            print(f"   -> Удалён volume из docker-compose.yml: {line.strip()}")
            removed = True
            continue
        new_lines.append(line)
    
    if removed:
        dc_path.write_text("\n".join(new_lines), encoding="utf-8")
    
    return removed


def clean_wp_config_volume():
    """
    Перед первым запуском убирает volume для wp-config.php из docker-compose.yml,
    если локального файла ./core/wp-config.php ещё не существует.
    Это предотвращает ситуацию, когда Docker монтирует пустоту.
    """
    config_path = Path("core/wp-config.php")
    if not config_path.exists():
        removed = remove_volume_from_compose("/var/www/html/wp-config.php")
        if removed:
            print("   -> wp-config.php volume удалён (файла ещё нет, будет добавлен после извлечения)")
        # Также удаляем сам файл если Docker создал пустую директорию
        if config_path.exists() and config_path.is_dir():
            config_path.rmdir()


def extract_wp_config():
    """
    Извлекает wp-config.php из контейнера WordPress наружу в ./core/wp-config.php,
    добавляет volume в docker-compose.yml и пересоздаёт контейнер.
    """
    print("\n=== 4/4: Извлечение wp-config.php ===")
    
    config_path = Path("core/wp-config.php")
    
    # Если файл уже существует — значит уже извлекали ранее
    if config_path.exists():
        print(f"   -> {config_path} уже существует. Пропуск извлечения.")
        # Убеждаемся что volume есть в docker-compose
        add_volume_to_compose("./core/wp-config.php:/var/www/html/wp-config.php")
        return
    
    # Убеждаемся что папка core существует
    config_path.parent.mkdir(parents=True, exist_ok=True)
    
    container_name = os.getenv("CONTAINER_NAME", "wp_site")
    
    # 1. Копируем wp-config.php из контейнера
    print(f"   -> Копирование wp-config.php из контейнера {container_name}...")
    try:
        subprocess.run(
            ["docker", "cp", f"{container_name}:/var/www/html/wp-config.php", str(config_path)],
            check=True, capture_output=True, text=True
        )
        print(f"   -> wp-config.php скопирован в {config_path}")
    except subprocess.CalledProcessError as e:
        print(f"   [!] Ошибка при копировании wp-config.php: {e.stderr}")
        return
    
    # 2. Добавляем volume в docker-compose.yml
    added = add_volume_to_compose("./core/wp-config.php:/var/www/html/wp-config.php")
    
    if not added:
        return
    
    # 3. Пересоздаём контейнер WordPress с новым volume
    print("   -> Пересоздание контейнера WordPress с проброшенным wp-config.php...")
    try:
        process = subprocess.Popen(
            DOCKER_COMPOSE_COMMAND + ["up", "-d", WORDPRESS_SERVICE],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        stdout, stderr = process.communicate()
        if process.returncode != 0:
            print(f"   [!] Ошибка пересоздания: {stderr}")
        else:
            print("   -> Контейнер пересоздан с проброшенным wp-config.php")
    except Exception as e:
        print(f"   [!] Ошибка: {e}")


def start_docker():
    print("\n=== 2/3: Запуск Docker и настройка прав ===")

    # создание внутренней сети, если она отсутствует
    try:
        subprocess.run(["docker", "network", "inspect", "web_network"], 
                       check=True, capture_output=True)
    except subprocess.CalledProcessError:
        print("Creating web_network...")
        subprocess.run(["docker", "network", "create", "web_network"])
    
    # 1. Попытка запуска контейнеров с перехватом ошибок порта
    try:
        # Используем Popen, чтобы "прочитать" ошибку до того, как скрипт упадет
        process = subprocess.Popen(
            DOCKER_COMPOSE_COMMAND + ["--profile", "dev", "up", "-d"],
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
    # fix_perm_cmd = "chown -R www-data:www-data /var/www/html/wp-content"
    # run_command(
    #     DOCKER_COMPOSE_COMMAND + ["exec", "-u", "root", WORDPRESS_SERVICE, "bash", "-c", fix_perm_cmd],
    #     "Не удалось изменить права доступа."
    # )
    print("\n=== 2.5/3: Тонкая настройка прав внутри контейнера ===")
    
    # Объединяем команды в одну строку для bash -c
    # 1. меняем владельца на www-data (чтобы работал WP)
    # 2. даем права группе на запись (для доступа)
    # 3. даем права на выполнение скриптам (если нужно)
    commands = (
        "chown -R www-data:www-data /var/www/html/wp-content && "
        "chmod -R 775 /var/www/html/wp-content" # 775 = rwxrwxr-x (владелец и группа могут всё)
    )

    try:
        run_command(
            DOCKER_COMPOSE_COMMAND + ["exec", "-u", "root", WORDPRESS_SERVICE, "bash", "-c", commands],
            "Не удалось настроить права доступа внутри контейнера."
        )
        print("   -> Права успешно синхронизированы.")
    except Exception as e:
        print(f"   -> [!] Предупреждение по правам: {e} (на Windows это иногда нормально)")

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
        # os.remove(json_path)
        run_command(
            DOCKER_COMPOSE_COMMAND + ["exec", "-u", "root", WORDPRESS_SERVICE, "rm", "/var/www/html/wp-content/uploads/temp_wp.json"],
            "Не удалось удалить временный JSON файл через Docker."
        )
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

    # Убираем volume для wp-config.php если файла ещё нет
    # (предотвращает монтирование пустоты при первом запуске)
    clean_wp_config_volume()

    start_docker()
    run_setup_script()

    # Извлекаем wp-config.php и пересоздаём контейнер с volume
    extract_wp_config()

    print("\n=====================================================")
    print("УСПЕШНОЕ ЗАВЕРШЕНИЕ!")
    print("=====================================================")

if __name__ == "__main__":
    main()