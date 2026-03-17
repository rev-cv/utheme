import re
import random
from pathlib import Path

# путь к файлу конфигурации
SCSS_FILE_PATH = Path(__file__).parent / "utheme" / "src" / "conf.scss"

# Словарь переменных.
# Ключ: имя переменной в SCSS (без знака $).
# Значение: список возможных вариантов (без кавычек, они добавятся автоматически).
THEME_CONFIG = {
    "main-menu": [
        "island", "aside", "marquee", "boring", 
        "docs", "circle", "newspaper", "console", "dynamic"
    ],
    "footer-menu": [
        "2columns", "central"
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
    "is-img_contain": [
        "true", "false"
    ],
    "is-left-align": [
        "true", "false"
    ],
    "is-border": [
        "true", "false"
    ],
    # Список шрифтовых пар (взято из scheme.fonts.scss)
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
        "luxury", # приглушенный
        "neon", # яркий
        "corporate", # стандарт
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

# переменные, значения которых НЕ нужно оборачивать в кавычки
NO_QUOTE_VARS = {"density-factor", "seed-hue", "font-size"}

# ==============================================================================
# ЛОГИКА ВЫПОЛНЕНИЯ
# ==============================================================================

def randomize_scss_variables(file_path, config):
    path = Path(file_path)
    
    if not path.exists():
        print(f"[ERROR] Файл не найден: {path}")
        return

    content = path.read_text(encoding='utf-8')
    original_content = content
    changes_made = False

    print(f"--- Обработка файла: {path.name} ---")

    for var_name, options in config.items():
        if not options:
            continue

        # Выбираем случайное значение
        if isinstance(options, tuple) and len(options) == 2:
            min_val, max_val = options
            if isinstance(min_val, int) and isinstance(max_val, int):
                new_val = random.randint(min_val, max_val)
            else:
                new_val = round(random.uniform(min_val, max_val), 2)
        else:
            new_val = random.choice(options)
        
        # если переменная текстовая (не в списке исключений), оборачиваем в кавычки
        replacement_val = f'"{new_val}"' if var_name not in NO_QUOTE_VARS else str(new_val)

        # Regex ищет: $имя-переменной: <значение>;
        # Группы: 
        # 1: начало строки ($var: )
        # 2: текущее значение (жадный поиск до ;)
        # 3: точка с запятой (;)
        pattern = re.compile(rf'(\${re.escape(var_name)}:\s*)(.+?)(;)')
        
        def replacement(match):
            nonlocal changes_made
            current_val = match.group(2)
            # Если значение отличается, логируем и меняем
            if current_val.strip() != replacement_val:
                print(f"  CHANGE ${var_name}: {current_val} -> {replacement_val}")
                changes_made = True
            return f"{match.group(1)}{replacement_val}{match.group(3)}"

        content = pattern.sub(replacement, content)

    if changes_made:
        path.write_text(content, encoding='utf-8')
        print("\n[SUCCESS] Файл успешно обновлен!")
    else:
        print("\n[INFO] Изменений не внесено (выпали те же значения).")

if __name__ == "__main__":
    randomize_scss_variables(SCSS_FILE_PATH, THEME_CONFIG)