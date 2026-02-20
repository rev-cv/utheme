def link_images_to_articles(articles_list: list[dict], images_list: list[dict]) -> list[dict]:
    """
    Сопоставляет список картинок со списком статей по пути к HTML файлу.
    
    :param articles_list: Список словарей статей (поле 'resource')
    :param images_list: Список словарей картинок (поле 'html')
    :return: Обновленный список статей с добавленным полем 'images'
    """
    # Создаем копию списка статей, чтобы не мутировать исходный (хорошая практика)
    updated_articles = []

    for article in articles_list:
        # Создаем новый словарь статьи и инициализируем пустой список для картинок
        article_copy = article.copy()
        article_copy['images'] = []
        
        article_path = article.get('resource')

        for image in images_list:
            image_html_path = image.get('html')
            
            # Сравниваем пути. Pathlib корректно сравнивает WindowsPath объекты.
            if article_path == image_html_path:
                article_copy['images'].append(image)
        
        updated_articles.append(article_copy)

    return updated_articles