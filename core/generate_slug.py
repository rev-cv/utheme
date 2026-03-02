import re
from transliterate import translit

def generate_slug(text):
    """
    Создает чистый латинский slug из любого текста (греческий, кириллица и т.д.)
    """
    # 1. Отсекаем лишнее после разделителей
    title_part = re.split(r'[|\-:\?\.!,()—–]', text)[0].strip()
    
    # 2. Транслитерируем в латиницу (автоматически определяет язык)
    try:
        # Если греческий или кириллица — переводим в латиницу
        slug = translit(title_part, reversed=True)
    except:
        # Если транслитерация не поддерживается для этого языка, оставляем как есть
        slug = title_part

    # 3. В нижний регистр
    slug = slug.lower()
    
    # 4. Заменяем специфические греческие/другие символы, если транслит пропустил
    # Например, иногда 'и' или 'й' могут вести себя странно
    
    # 5. Оставляем только латиницу, цифры и дефисы
    slug = re.sub(r'[^a-z0-9\s-]', '', slug)
    
    # 6. Пробелы в дефисы, удаляем дубли дефисов
    slug = re.sub(r'[\s-]+', '-', slug).strip('-')
    
    return slug


# словарь стоп-слов
STOP_WORDS = {
    'ru': {'и', 'в', 'во', 'на', 'с', 'со', 'но', 'а', 'для', 'как', 'что', 'это', 'по', 'за', 'из', 'от', 'до', 'без'},
    'en': {'a', 'an', 'the', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'},
    'fr': {'le', 'la', 'les', 'un', 'une', 'des', 'et', 'ou', 'mais', 'dans', 'en', 'par', 'pour', 'avec', 'sur'},
    'de': {'der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'aber', 'in', 'auf', 'zu', 'für', 'mit', 'von', 'aus'},
    'es': {'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'y', 'o', 'pero', 'en', 'con', 'por', 'para', 'de'},
    'it': {'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'e', 'o', 'ma', 'in', 'con', 'per', 'da', 'di'},
    'pt': {'o', 'a', 'os', 'as', 'um', 'uma', 'e', 'ou', 'mas', 'em', 'com', 'por', 'para', 'de', 'do', 'da'},
    'nl': {'de', 'het', 'een', 'en', 'of', 'maar', 'in', 'op', 'te', 'voor', 'met', 'van', 'door'},
    'pl': {'i', 'w', 'na', 'z', 'do', 'ze', 'ale', 'lub', 'czy', 'dla', 'o', 'za'},
    'cz': {'a', 'i', 'v', 'na', 's', 'z', 'do', 'ale', 'nebo', 'pro', 'o', 'za'},
    'sk': {'a', 'i', 'v', 'na', 's', 'z', 'do', 'ale', 'alebo', 'pre', 'o', 'za'},
    'hu': {'a', 'az', 'egy', 'es', 'vagy', 'de', 'ban', 'ben', 'ba', 'be', 'rol', 'rel'},
    'ro': {'si', 'sau', 'dar', 'in', 'pe', 'la', 'pentru', 'cu', 'de', 'un', 'o', 'ul', 'le'},
    'bg': {'и', 'в', 'на', 'с', 'за', 'че', 'но', 'или', 'от', 'до'},
    'gr': {'ο', 'η', 'το', 'οι', 'τα', 'και', 'ή', 'αλλά', 'σε', 'με', 'για', 'από', 'τον', 'την'},
    'nordic': {'og', 'en', 'et', 'den', 'det', 'de', 'i', 'på', 'til', 'for', 'med', 'av', 'ja', 'nej'}, # SV, DA, FI, NO
    'baltic': {'un', 'ir', 'uz', 'ar', 'i', 'v', 'su', 'be', 'nuo'} # LV, LT, ET
}

def generate_universal_slug(text, max_words=6, max_chars=70):
    # 1. Отсекаем лишнее после разделителей (как в вашем коде)
    title_part = re.split(r'[|\-:\?\.!,()—–]', text)[0].strip()
    
    # 2. Нижний регистр и разбивка на слова
    words = title_part.lower().split()
    
    # 3. Собираем единый сет стоп-слов
    all_stops = set().union(*STOP_WORDS.values())
    
    # 4. Фильтруем стоп-слова
    filtered_words = [w for w in words if w not in all_stops]
    
    # Если заголовок стал пустым после фильтрации — возвращаем оригинал
    if not filtered_words:
        filtered_words = words
        
    # Ограничиваем количество слов
    filtered_words = filtered_words[:max_words]
    clean_text = " ".join(filtered_words)

    # 5. Транслитерация (поддерживает RU и GR из коробки в библиотеке transliterate)
    try:
        slug = translit(clean_text, reversed=True)
    except Exception:
        slug = clean_text

    # 6. Очистка: оставляем только латиницу и цифры
    slug = slug.lower()
    # Заменяем всё, что не буквы a-z и не цифры, на пробелы
    slug = re.sub(r'[^a-z0-9]', ' ', slug)
    
    # 7. Схлопываем пробелы в дефисы
    slug = re.sub(r'\s+', '-', slug).strip('-')
    
    # 8. Обрезка по длине (не ломая слова)
    if len(slug) > max_chars:
        trimmed = slug[:max_chars].rsplit('-', 1)
        slug = trimmed[0] if len(trimmed) > 1 else slug[:max_chars]

    return slug



def advanced_slugify(text, max_words=6, max_chars=60):
    # 1. Предварительная замена смысловых символов
    replacements = {
        '&': 'and', '@': 'at', '$': 'dollar', '€': 'euro', 
        '%': 'percent', '+': 'plus'
    }
    for char, replacement in replacements.items():
        text = text.replace(char, f' {replacement} ')

    # 2. Очистка от лишнего (до первой точки/скобки)
    title_part = re.split(r'[|\-:\?\.!,()—–]', text)[0].strip()

    # 3. Транслитерация (сначала немецкие умляуты и прочее)
    # Можно добавить кастомный маппинг перед translit
    try:
        slug = translit(title_part, reversed=True)
    except:
        slug = title_part

    slug = slug.lower()

    # 4. Оставляем только латиницу и цифры
    slug = re.sub(r'[^a-z0-9\s]', ' ', slug)
    
    # 5. Фильтрация стоп-слов (используем ваш сет STOP_WORDS)
    words = slug.split()
    all_stops = set().union(*STOP_WORDS.values())
    
    # Сохраняем слова, если они: не стоп-слова ИЛИ содержат цифры (iPhone15, 4K)
    filtered_words = [w for w in words if w not in all_stops or any(char.isdigit() for char in w)]
    
    if not filtered_words:
        filtered_words = words[:max_words]
    else:
        filtered_words = filtered_words[:max_words]

    # 6. Сборка
    result = "-".join(filtered_words)

    # 7. Умная обрезка по длине
    if len(result) > max_chars:
        result = result[:max_chars].rsplit('-', 1)[0]
    
    # Финальная проверка: если в конце остался висящий символ (типа "a", "i", "o")
    result = re.sub(r'-[a-z]$', '', result)

    return result