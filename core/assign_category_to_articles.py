
def assign_category_to_articles(category_name: str, articles_list: list[dict]) -> list[dict]:
    """
    Добавляет указанную категорию ко всем статьям в списке.
    """
    if not category_name:
        print("    Название категории не указано, пропуск.")
        return articles_list

    print(f"\nПрисвоение категории '{category_name}' статьям...")
    for article in articles_list:
        article['cat'] = category_name
    print(f"    Категория '{category_name}' присвоена {len(articles_list)} статьям.")
    return articles_list