import sys
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString

# --- Обработчики блоков ---

def _handle_heading(tag):
    """Обработка заголовков (h1-h6)."""
    level = tag.name
    level_int = int(level.replace('h', ''))
    # decode_contents сохраняет внутреннее форматирование (например, <em> или <strong>)
    content = tag.decode_contents()

    return f'<!-- wp:heading {{"level":{level_int}}} -->\n<{level} class="wp-block-heading">{content}</{level}>\n<!-- /wp:heading -->'

def _handle_paragraph(tag):
    """Обработка параграфов (p)."""
    content = tag.decode_contents()
    return f'<!-- wp:paragraph -->\n<p>{content}</p>\n<!-- /wp:paragraph -->'

def _handle_image(tag):
    """Обработка изображений (img)."""
    src = tag.get('src', '')
    alt = tag.get('alt', '')
    
    # Извлекаем имя файла без пути и расширения для использования в ID
    img_id = Path(src).stem
    
    # Формируем блок. src оставляем пустым, ID заполняем из имени файла.
    return f'<!-- wp:image -->\n<figure id="{img_id}" class="wp-block-image"><img src="" alt="{alt}"/></figure>\n<!-- /wp:image -->'


def _handle_list(tag):
    """Обработка списков (ul, ol)."""
    content = tag.decode_contents()
    if tag.name == 'ol':
        return f'<!-- wp:list {{"ordered":true}} -->\n<ol>{content}</ol>\n<!-- /wp:list -->'
    return f'<!-- wp:list -->\n<ul>{content}</ul>\n<!-- /wp:list -->'

BLOCK_HANDLERS = {
    'h1': _handle_heading, 'h2': _handle_heading, 'h3': _handle_heading,
    'h4': _handle_heading, 'h5': _handle_heading, 'h6': _handle_heading,
    'p': _handle_paragraph, 'img': _handle_image, 'ul': _handle_list, 'ol': _handle_list,
}

# --- Основная логика ---

def convert_html_to_blocks(html_content, add_post_meta=False):
    """Преобразует строку HTML в WP блоки."""
    soup = BeautifulSoup(html_content, 'html.parser')
    container = soup.find('article') or soup.find('main') or soup.body
    
    if not container:
        return ""

    # Предварительно распаковываем структурные теги (div, section и т.д.),
    # чтобы их содержимое стало прямыми потомками container
    for tag in container.find_all(['div', 'section', 'main', 'figure']):
        tag.unwrap()

    blocks = []
    for child in container.children:
        if isinstance(child, NavigableString):
            continue

        if child.name in BLOCK_HANDLERS:
            handler = BLOCK_HANDLERS[child.name]
            blocks.append(handler(child))
            
            if add_post_meta and child.name == 'h1':
                blocks.append('\n<!-- wp:shortcode -->[post_meta]<!-- /wp:shortcode -->\n')
        
    return "\n\n".join(blocks)

def conversion_init(pages_list: list[dict]) -> list[dict]:
    """
    Принимает список объектов, где 'resource' — это объект Path к .html файлу.
    Конвертирует содержимое в WP-блоки, сохраняет файл .wp рядом с исходным
    и добавляет содержимое блоков в поле 'wp_block' каждого объекта.
    """
    print('\nЗапуск конвертации HTML в WP блоки\n')
    
    updated_list = []

    for item in pages_list:
        new_item = item.copy()
        source_file = item.get("resource")

        # resource действительно Path и файл существует?
        if not isinstance(source_file, Path) or not source_file.exists():
            print(f"Ресурс не является валидным путем или файл отсутствует ({source_file})")
            sys.exit(1)

        try:
            display_path = source_file.relative_to(Path.cwd())
        except ValueError:
            display_path = source_file

        try:
            content = source_file.read_text(encoding='utf-8')
            
            # нужно ли в файл добавлять шорткод выводящий данные статьи?
            # если название папки начинается с CL, то добавлять шорткод во время конвертации
            add_meta = source_file.parent.name.startswith("CL")
            wp_content = convert_html_to_blocks(content, add_post_meta=add_meta)
            
            if wp_content:
                # сохранить результат конвертирования в файл.wp рядом с файлом .html
                output_file = source_file.with_suffix(".wp")
                output_file.write_text(wp_content, encoding='utf-8')
                
                # добавить результат конвертирования в объект
                new_item["wp_block"] = wp_content
                print(f"{display_path} -> {output_file.name}")
            else:
                new_item["wp_block"] = ""
                print(f"Файл {display_path} пуст после конвертации")

        except Exception as e:
            print(f"Ошибка в файле {display_path}: {e}")
            sys.exit(1)

        updated_list.append(new_item)

    print('\n' + '='*50)
    return updated_list
    