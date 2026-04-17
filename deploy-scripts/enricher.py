# /// script
# requires-python = ">=3.13"
# dependencies = [
#     "pyyaml",
# ]
# ///
"""
Скрипт обхода сайтов и запуска заполнения.

Расположение: /home/deploy/run_sites.py

Логика:
  1. Обходит папки внутри /home/deploy/sites/
  2. Пропускает сайты, уже отмеченные как успешные в results.yml
  3. Определяет какой скрипт запускать (ufirst.py или unfirst.py)
     по наличию папки spec/CLUSTERS MAIN/
  4. Запускает скрипт через `uv run`
  5. Если основной скрипт завершился успешно И в spec/ есть папка
     CLUSTERS ADD — дополнительно запускает u30.py
  6. Записывает результат (успех/ошибка) в results.yml
"""
import os
import subprocess
import sys
import yaml
from datetime import datetime, timezone
from pathlib import Path


# ─── Настройки ────────────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).parent
SITES_DIR = BASE_DIR / "sites"
RESULTS_FILE = BASE_DIR / "results.yml"

# Папки/файлы внутри sites/, которые не являются сайтами
IGNORE_NAMES = {"asana.py", "nginx-proxy-manager", "__pycache__", ".git"}


# ─── Утилиты ──────────────────────────────────────────────────────────────────
def load_results() -> dict:
    """Загружает results.yml или возвращает пустой словарь."""
    if RESULTS_FILE.exists():
        with open(RESULTS_FILE, "r", encoding="utf-8") as f:
            data = yaml.safe_load(f)
            return data if isinstance(data, dict) else {}
    return {}


def save_results(results: dict) -> None:
    """Сохраняет results.yml."""
    with open(RESULTS_FILE, "w", encoding="utf-8") as f:
        yaml.dump(results, f, default_flow_style=False, allow_unicode=True, sort_keys=False)


def detect_script(site_dir: Path) -> str | None:
    """
    Определяет какой скрипт запускать:
      - unfirst.py  если есть spec/CLUSTERS MAIN/
      - ufirst.py   если кластеры лежат прямо в spec/ (CL1, CL2 ...)
      - None        если ни один скрипт не найден в папке
    """
    spec_dir = site_dir / "spec"

    if not spec_dir.is_dir():
        return None

    # unfirst.py работает со структурой spec/CLUSTERS MAIN/CL1..CL5
    if (spec_dir / "CLUSTERS MAIN").is_dir():
        script = site_dir / "unfirst.py"
        return str(script) if script.exists() else None

    # ufirst.py работает со структурой spec/CL1..CL5
    has_clusters = any((spec_dir / f"CL{i}").is_dir() for i in range(1, 6))
    if has_clusters:
        script = site_dir / "ufirst.py"
        return str(script) if script.exists() else None

    return None


def run_site(site_dir: Path, script_path: str) -> tuple[bool, str]:
    cmd = ["uv", "run", script_path, "--docker-mode"]
    output_lines: list[str] = []
    env = {**os.environ, "PYTHONUNBUFFERED": "1"}

    try:
        process = subprocess.Popen(
            cmd,
            cwd=str(site_dir),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,  # stderr сливается в stdout
            env=env,
        )

        for raw_line in iter(process.stdout.readline, b""):
            line = raw_line.decode("utf-8", errors="replace")
            print(f"  │ {line}", end="", flush=True)  # вывод в реальном времени
            output_lines.append(line)

        process.wait(timeout=600)
        return process.returncode == 0, "".join(output_lines).strip()

    except subprocess.TimeoutExpired:
        process.kill()
        return False, "Таймаут: скрипт не завершился за 10 минут"
    except Exception as e:
        return False, f"Ошибка запуска: {e}"


# ─── Вспомогательные функции для интерактивного режима ───────────────────────
def is_done(results: dict, site_name: str) -> bool:
    return results.get(site_name, {}).get("status") == "success"


def ask_yes_no(prompt: str) -> bool:
    while True:
        answer = input(f"{prompt} [y/n]: ").strip().lower()
        if answer in ("y", "yes", "д", "да"):
            return True
        if answer in ("n", "no", "н", "нет"):
            return False


def all_site_dirs() -> list[Path]:
    return sorted(
        d for d in SITES_DIR.iterdir()
        if d.is_dir() and d.name not in IGNORE_NAMES
    )


# ─── Основной цикл ───────────────────────────────────────────────────────────
def main() -> None:
    if not SITES_DIR.is_dir():
        print(f"Папка {SITES_DIR} не найдена")
        sys.exit(1)

    results = load_results()
    cli_args = sys.argv[1:]

    if cli_args:
        # ── Режим с явным указанием сайтов ──────────────────────────────────
        site_dirs = []
        for name in cli_args:
            d = SITES_DIR / name
            if not d.is_dir():
                print(f"[ОШИБКА]  Папка не найдена: {d}")
                sys.exit(1)
            if is_done(results, name):
                if not ask_yes_no(f"Сайт «{name}» уже успешно заполнен. Запустить снова?"):
                    print(f"[ПРОПУСК]  {name}")
                    continue
                # Сбрасываем статус, чтобы скрипт не пропустил сайт
                results.pop(name, None)
            site_dirs.append(d)

        if not site_dirs:
            print("Нет сайтов для обработки.")
            return
    else:
        # ── Интерактивный режим: список всех сайтов ──────────────────────────
        all_dirs = all_site_dirs()
        if not all_dirs:
            print("Нет папок с сайтами")
            return

        pending = []
        for d in all_dirs:
            status = "ok" if is_done(results, d.name) else "no"
            print(f"  {d.name} - {status}")
            if status == "no":
                pending.append(d)

        print()
        if not pending:
            print("Все сайты уже успешно заполнены.")
            return

        if not ask_yes_no(f"Запустить скрипт для {len(pending)} сайтов со статусом «no»?"):
            print("Отменено.")
            return

        site_dirs = pending

    skipped = 0
    succeeded = 0
    failed = 0
    failed_sites: list[str] = []

    for site_dir in site_dirs:
        site_name = site_dir.name

        # Пропускаем уже успешно заполненные
        if site_name in results and results[site_name].get("status") == "success":
            print(f"[ПРОПУСК]  {site_name} — уже заполнен")
            skipped += 1
            continue

        # Если основной скрипт прошёл, но u30 упал — повторяем только u30
        if site_name in results and results[site_name].get("status") == "error_u30":
            u30_script = site_dir / "u30.py"
            if u30_script.exists():
                print(f"[ПОВТОР]   {site_name} → u30.py (предыдущая попытка упала)")
                success_u30, output_u30 = run_site(site_dir, str(u30_script))
                now = datetime.now(timezone.utc).isoformat(timespec="seconds")

                if success_u30:
                    print(f"[УСПЕХ]    {site_name} → u30.py")
                    succeeded += 1
                    results[site_name]["status"] = "success"
                    results[site_name]["finished_at"] = now
                    results[site_name].pop("error_tail", None)
                else:
                    print(f"[ОШИБКА]   {site_name} → u30.py")
                    failed += 1
                    failed_sites.append(f"{site_name} (u30.py)")
                    results[site_name]["finished_at"] = now
                    results[site_name]["error_tail"] = output_u30[-500:] if output_u30 else "нет вывода"

                save_results(results)
            continue

        # Определяем скрипт
        script_path = detect_script(site_dir)
        if script_path is None:
            print(f"[ПРОПУСК]  {site_name} — не удалось определить скрипт")
            continue

        script_name = Path(script_path).name
        print(f"[ЗАПУСК]   {site_name} → {script_name}")

        success, output = run_site(site_dir, script_path)
        now = datetime.now(timezone.utc).isoformat(timespec="seconds")

        if success:
            # Проверяем нужен ли дополнительный скрипт u30.py
            u30_script = site_dir / "u30.py"
            clusters_add = site_dir / "spec" / "CLUSTERS ADD"

            if clusters_add.is_dir() and u30_script.exists():
                print(f"[ЗАПУСК]   {site_name} → u30.py (CLUSTERS ADD)")
                success_u30, output_u30 = run_site(site_dir, str(u30_script))
                now = datetime.now(timezone.utc).isoformat(timespec="seconds")

                if success_u30:
                    print(f"[УСПЕХ]    {site_name} → u30.py")
                    succeeded += 1
                    results[site_name] = {
                        "status": "success",
                        "script": script_name,
                        "u30": True,
                        "finished_at": now,
                    }
                else:
                    print(f"[ОШИБКА]   {site_name} → u30.py")
                    failed += 1
                    failed_sites.append(f"{site_name} (u30.py)")
                    results[site_name] = {
                        "status": "error_u30",
                        "script": script_name,
                        "u30": True,
                        "finished_at": now,
                        "error_tail": output_u30[-500:] if output_u30 else "нет вывода",
                    }
            else:
                print(f"[УСПЕХ]    {site_name}")
                succeeded += 1
                results[site_name] = {
                    "status": "success",
                    "script": script_name,
                    "u30": False,
                    "finished_at": now,
                }
        else:
            print(f"[ОШИБКА]   {site_name}")
            failed += 1
            failed_sites.append(site_name)
            results[site_name] = {
                "status": "error",
                "script": script_name,
                "finished_at": now,
                "error_tail": output[-500:] if output else "нет вывода",
            }

        # Сохраняем после каждого сайта чтобы не потерять прогресс
        save_results(results)

    # ─── Итоги ────────────────────────────────────────────────────────────
    print("\n" + "=" * 60)
    print(f"Всего сайтов:  {len(site_dirs)}")
    print(f"  пропущено:   {skipped}")
    print(f"  успешно:     {succeeded}")
    print(f"  с ошибками:  {failed}")

    if failed_sites:
        print("\nСайты с ошибками:")
        for name in failed_sites:
            print(f"  - {name}")

    print(f"\nРезультаты сохранены в {RESULTS_FILE}")


if __name__ == "__main__":
    main()