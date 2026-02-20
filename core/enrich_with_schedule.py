import re, random
from datetime import datetime, timedelta

def enrich_with_schedule(articles, rule_str):
    # парсинг правила
    m = re.search(r"(\d+)d\s+(\d+(?:-\d+)?)p(?:\s+\((\d+)-(\d+)\))?", rule_str)
    if not m: return articles

    days_step = int(m.group(1)) + 1
    p_range = [int(x) for x in m.group(2).split('-')]
    min_p, max_p = p_range[0], p_range[-1]
    h_start, h_end = int(m.group(3) or 9), int(m.group(4) or 21)

    # 2. Подготовка "курсора" времени
    current_day = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    scheduled_articles = []
    temp_queue = []

    for article in articles:
        # Если очередь пуста, генерируем слоты на новый доступный день
        while not temp_queue:
            n_posts = random.randint(min_p, max_p)
            if n_posts > 0:
                sector = ((h_end - h_start) * 60) // n_posts
                for i in range(n_posts):
                    minutes = (h_start * 60) + (i * sector) + random.randint(0, sector - 1)
                    dt = current_day + timedelta(minutes=minutes)
                    if dt > datetime.now(): # Только будущее время
                        temp_queue.append(dt)
            current_day += timedelta(days=days_step)

        # Берем первое время из очереди и "обогащаем" объект
        article['publish_at'] = temp_queue.pop(0)
        # print(article['publish_at'])
        scheduled_articles.append(article)

    return scheduled_articles

# --- ПРИМЕР ---
# data = [{"id": 1}, {"id": 2}, {"id": 3}, {"id": 4}, {"id": 5}]
# enriched_data = enrich_with_schedule(data, "1d 2-3p (10-18)")

# for item in enriched_data:
#     print(f"ID {item['id']} выйдет: {item['publish_at']}")