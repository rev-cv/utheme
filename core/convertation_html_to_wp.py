import re
import sys
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString


def _preprocess_faq_blocks(html: str) -> str:
    """Конвертирует шорткод [faq]...[/faq] в HTML <details> теги, поддерживая разные структуры."""

    def replace_faq_block(match: re.Match) -> str:
        block_content = match.group(1)
        
        # Находим отдельные блоки [id="..." ...]
        # Используем ленивый квантификатор .*? для захвата содержимого скобок
        items = re.findall(r'(\[id="[^"]*".*?\])', block_content)
        
        details_tags = []
        for item in items:
            # Пытаемся извлечь содержимое для заголовка (question или title)
            # и для описания (answer или desc)
            question = re.search(r'(?:question|title)="([^"]*)"', item)
            answer = re.search(r'(?:answer|desc)="([^"]*)"', item)
            
            if question and answer:
                q_text = question.group(1)
                a_text = answer.group(1)
                details_tags.append(
                    f'<details><summary>{q_text}</summary><p>{a_text}</p></details>'
                )
        return '\n'.join(details_tags)

    return re.sub(r'\[faq\](.*?)\[/faq\]', replace_faq_block, html, flags=re.DOTALL)

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
    classes = tag.get('class', [])
    if 'hero-subtitle' in classes:
        return f'<!-- wp:paragraph {{"className":"hero-subtitle"}} -->\n<p class="hero-subtitle">{content}</p>\n<!-- /wp:paragraph -->'

    return f'<!-- wp:paragraph -->\n<p>{content}</p>\n<!-- /wp:paragraph -->'

def _handle_image(tag):
    """Обработка изображений (img)."""
    src = tag.get('src', '')
    alt = tag.get('alt', '')
    fname = Path(src).stem + ".webp"
    return (
        f'<!-- wp:image {{"id":%%IMGID:{fname}%%,"sizeSlug":"full","linkDestination":"none"}} -->\n'
        f'<figure class="wp-block-image size-full">'
        f'<img class="wp-image-%%IMGID:{fname}%%" src="%%IMGSRC:{fname}%%" alt="{alt}"/>'
        f'</figure>\n'
        f'<!-- /wp:image -->'
    )

def _handle_list(tag):
    """Обработка списков (ul, ol)."""
    content = tag.decode_contents()
    if tag.name == 'ol':
        return f'<!-- wp:list {{"ordered":true}} -->\n<ol>{content}</ol>\n<!-- /wp:list -->'
    return f'<!-- wp:list -->\n<ul>{content}</ul>\n<!-- /wp:list -->'

def _handle_table(tag):
    """Обработка таблиц (table)."""
    content = tag.decode_contents()
    return f'<!-- wp:table -->\n<figure class="wp-block-table"><table class="has-fixed-layout">{content}</table></figure>\n<!-- /wp:table -->'

def _handle_details(tag):
    """Обработка <details> (аккордеон)."""
    summary_tag = tag.find('summary')
    summary_text = summary_tag.get_text(strip=True) if summary_tag else ''
    
    if summary_tag:
        summary_tag.extract()

    # Распаковываем обертки, чтобы добраться до контентных тегов
    for wrapper in tag.find_all(['div', 'section']):
        wrapper.unwrap()

    # Конвертируем дочерние элементы в WP-блоки
    inner_blocks = []
    for child in tag.children:
        if isinstance(child, NavigableString) and not child.strip():
            continue

        if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
            handler = BLOCK_HANDLERS[child.name]
            inner_blocks.append(handler(child))
        # Неизвестные теги просто игнорируются, как в основной функции

    inner_blocks_content = '\n'.join(inner_blocks)
    
    return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{summary_text}</summary>{inner_blocks_content}</details>\n<!-- /wp:details -->'

_CALLOUT_CLASS_MAP = {
    # canonical names (pass-through)
    'info-callout':     'info-callout',
    'success-callout':  'success-callout',
    'warning-callout':  'warning-callout',
    'danger-callout':   'danger-callout',
    # legacy / alias → info-callout
    'info-box':   'info-callout',
    'fun-fact':   'info-callout',
    'center-box': 'info-callout',
    'banner-box': 'info-callout',
    'task-box':   'info-callout',
    # legacy → typed callouts
    'callout':    'danger-callout',
    'error-box':  'danger-callout',
    'warning-box': 'warning-callout',
    'success-box': 'success-callout',
}

def _make_callout_block(tag, callout_type):
    inner_blocks = []
    for child in tag.children:
        if isinstance(child, NavigableString) and not child.strip():
            continue
        if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
            inner_blocks.append(BLOCK_HANDLERS[child.name](child))
    inner_content = '\n'.join(inner_blocks)
    return (
        f'<!-- wp:group {{"tagName":"section","className":"callout {callout_type}",'
        f'"layout":{{"type":"constrained"}}}} -->\n'
        f'<section class="wp-block-group callout {callout_type}">'
        f'<div class="wp-block-group__inner-container">{inner_content}</div>'
        f'</section>\n<!-- /wp:group -->'
    )

def _handle_div(tag):
    classes = tag.get('class', [])

    for cls in classes:
        if cls in _CALLOUT_CLASS_MAP:
            return _make_callout_block(tag, _CALLOUT_CLASS_MAP[cls])

    if 'card-grid' in classes:
        """Обработка карточки внутри grid"""
        grid_items = []
        for card_div in tag.find_all('div', recursive=False):
            card_blocks = []
            for child in card_div.children:
                if isinstance(child, NavigableString) and not child.strip():
                    continue

                if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                    handler = BLOCK_HANDLERS[child.name]
                    card_blocks.append(handler(child))
            
            if card_blocks:
                card_content = '\n'.join(card_blocks)
                item_block = f'<!-- wp:group {{"className":"card-grid-item","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group card-grid-item">{card_content}</div>\n<!-- /wp:group -->'
                grid_items.append(item_block)

        inner_content = '\n'.join(grid_items)
        
        # Используем нативный Grid (type: grid) с минимальной шириной колонки для адаптивности
        return f'<!-- wp:group {{"className":"card-grid","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group card-grid"><div class="wp-block-group__inner-container">{inner_content}</div></div>\n<!-- /wp:group -->'
    
    elif any(cls in tag.get('class', []) for cls in ['at-a-glance',]):
        """Обработка карточек табличного вида"""
        grid_items = []
        for card_div in tag.find_all('div', recursive=False):
            card_blocks = []
            for child in card_div.children:
                if isinstance(child, NavigableString) and not child.strip():
                    continue

                if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                    handler = BLOCK_HANDLERS[child.name]
                    card_blocks.append(handler(child))
            
            if card_blocks:
                card_content = '\n'.join(card_blocks)
                item_block = f'<!-- wp:group {{"className":"table-grid-item","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group table-grid-item">{card_content}</div>\n<!-- /wp:group -->'
                grid_items.append(item_block)

        inner_content = '\n'.join(grid_items)
        
        # Используем 12rem (~192px), чтобы 5 карточек могли поместиться в ряд на широком экране (5*192 = 960px)
        return f'<!-- wp:group {{"className":"table-grid","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group table-grid"><div class="wp-block-group__inner-container">{inner_content}</div></div>\n<!-- /wp:group -->'
    
    elif any(cls in tag.get('class', []) for cls in ['dos-donts',]):
        """Обработка блоков yes/no"""
        columns = tag.find_all('div', recursive=False)
        if len(columns) != 2:
            return ""

        # Column 1 (YES)
        yes_col = columns[0]
        yes_blocks = []
        for child in yes_col.children:
            if isinstance(child, NavigableString) and not child.strip():
                continue
            if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                handler = BLOCK_HANDLERS[child.name]
                yes_blocks.append(handler(child))
        
        yes_content = '\n'.join(yes_blocks)
        yes_block = f'<!-- wp:group {{"className":"yesno-box__yes","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group yesno-box__yes"><div class="wp-block-group__inner-container">{yes_content}</div></div>\n<!-- /wp:group -->'

        # Column 2 (NO)
        no_col = columns[1]
        no_blocks = []
        for child in no_col.children:
            if isinstance(child, NavigableString) and not child.strip():
                continue
            if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                handler = BLOCK_HANDLERS[child.name]
                no_blocks.append(handler(child))

        no_content = '\n'.join(no_blocks)
        no_block = f'<!-- wp:group {{"className":"yesno-box__no","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group yesno-box__no"><div class="wp-block-group__inner-container">{no_content}</div></div>\n<!-- /wp:group -->'

        # Main container
        inner_content = f'{yes_block}\n{no_block}'
        return f'<!-- wp:group {{"className":"yesno-box","layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group yesno-box"><div class="wp-block-group__inner-container">{inner_content}</div></div>\n<!-- /wp:group -->'

    elif any(cls in tag.get('class', []) for cls in ['odds-example', 'key-takeaways', 'worked-example', 'key-takeaway', 'glossary-term', 'pre-bet-checklist']):
        """Обработка простых секционных div'ов (odds-example, key-takeaways, glossary-term и др.)."""
        tag_classes = tag.get('class', [])
        # Находим первый совпавший класс, чтобы использовать его в выводе
        section_classes = ['odds-example', 'key-takeaways', 'worked-example', 'key-takeaway', 'glossary-term', 'pre-bet-checklist']
        matched_class = next(cls for cls in section_classes if cls in tag_classes)

        inner_blocks = []
        for child in tag.children:
            if isinstance(child, NavigableString) and not child.strip():
                continue

            if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                handler = BLOCK_HANDLERS[child.name]
                inner_blocks.append(handler(child))

        inner_content = '\n'.join(inner_blocks)
        return f'<!-- wp:group {{"tagName":"section","className":"{matched_class}","layout":{{"type":"constrained"}}}} -->\n<section class="wp-block-group {matched_class}"><div class="wp-block-group__inner-container">{inner_content}</div></section>\n<!-- /wp:group -->'

    elif 'faq-item' in tag.get('class', []):
        """Обработка FAQ-элемента как аккордеона (wp:details)."""
        btn = tag.find('button')
        summary_text = btn.get_text(strip=True) if btn else ''

        faq_a_div = tag.find('div', class_='faq-a')
        inner_blocks = []
        if faq_a_div:
            for child in faq_a_div.children:
                if isinstance(child, NavigableString) and not child.strip():
                    continue
                if hasattr(child, 'name') and child.name in BLOCK_HANDLERS:
                    inner_blocks.append(BLOCK_HANDLERS[child.name](child))

        inner_content = '\n'.join(inner_blocks)
        return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{summary_text}</summary>{inner_content}</details>\n<!-- /wp:details -->'

    return ""

def _handle_span(tag):
    """Обработка span (превращение в p для hero-label)."""
    classes = tag.get('class', [])
    if 'hero-label' in classes:
        content = tag.decode_contents()
        return f'<!-- wp:paragraph {{"className":"hero-label"}} -->\n<p class="hero-label">{content}</p>\n<!-- /wp:paragraph -->'
    return ""

def _handle_figure(tag):
    """Обработка figure (img + опциональный figcaption)."""
    img_tag = tag.find('img')
    if not img_tag:
        return ""

    src = img_tag.get('src', '')
    alt = img_tag.get('alt', '')
    fname = Path(src).stem + ".webp"

    figcaption_tag = tag.find('figcaption')
    caption = (
        f'<figcaption class="wp-element-caption">{figcaption_tag.get_text(strip=True)}</figcaption>'
        if figcaption_tag else ''
    )
    return (
        f'<!-- wp:image {{"id":%%IMGID:{fname}%%,"sizeSlug":"full","linkDestination":"none"}} -->\n'
        f'<figure class="wp-block-image size-full">'
        f'<img class="wp-image-%%IMGID:{fname}%%" src="%%IMGSRC:{fname}%%" alt="{alt}"/>'
        f'{caption}</figure>\n'
        f'<!-- /wp:image -->'
    )

BLOCK_HANDLERS = {
    'h1': _handle_heading, 'h2': _handle_heading, 'h3': _handle_heading,
    'h4': _handle_heading, 'h5': _handle_heading, 'h6': _handle_heading,
    'p': _handle_paragraph, 'img': _handle_image, 'figure': _handle_figure,
    'ul': _handle_list, 'ol': _handle_list,
    'table': _handle_table, 'details': _handle_details, 'div': _handle_div, 'span': _handle_span,
}

# --- Основная логика ---

def convert_html_to_blocks(html_content, add_post_meta=False):
    """Преобразует строку HTML в WP блоки."""
    html_content = _preprocess_faq_blocks(html_content)
    soup = BeautifulSoup(html_content, 'html.parser')
    container = soup.find('article') or soup.find('main') or soup.body
    
    if not container:
        return ""

    # внешние ссылки → новая вкладка; внутренние → шорткод $$LINK slug | text$$
    for a_tag in container.find_all('a', href=True):
        href = a_tag['href']
        if href.startswith(('http://', 'https://', 'mailto:', 'tel:', '#', '//', 'javascript:')):
            a_tag['target'] = '_blank'
            a_tag['rel'] = 'noopener'
        else:
            from urllib.parse import urlparse
            path = urlparse(href).path.strip('/')
            if not path:
                continue
            slug = Path(path.split('/')[-1]).stem
            if not slug:
                continue
            link_text = a_tag.get_text(strip=True)
            if not link_text:
                continue
            a_tag.replace_with(f'$$LINK {slug} | {link_text}$$')

    
    # все DIV распаковываются и удаляются за исключением тех, что в этом списке
    forbidden_div = [
        *_CALLOUT_CLASS_MAP.keys(),
        'card-grid', 'at-a-glance', 'dos-donts', 'odds-example', 'key-takeaways',
        'worked-example', 'faq-item', 'key-takeaway', 'glossary-term', 'pre-bet-checklist',
    ]

    # удаляем оглавления (nav с data-content="toc") до любой обработки
    for toc in container.find_all('nav', attrs={'data-content': 'toc'}):
        toc.decompose()

    # figure не распаковывается — обрабатывается через _handle_figure (сохраняет figcaption)
    # nav распаковывается, чтобы его содержимое (p, ol) попало в основной поток
    for tag in container.find_all(['div', 'section', 'main', 'nav']):
        if tag.name == 'div' and any(cls in tag.get('class', []) for cls in forbidden_div):
            continue
        
        # не находится ли тег внутри защищенного блока (например, div внутри card-grid) ?
        is_protected = False
        for parent in tag.parents:
            if parent.name == 'div' and any(cls in parent.get('class', []) for cls in forbidden_div):
                is_protected = True
                break
            if parent is container:
                break
        
        if is_protected:
            continue

        # распаковывка структурных тегов (div, section и т.д.),
        # чтобы их содержимое стало прямыми потомками container
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
    print('\nЗапуск конвертации в WP блоки\n')
    
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
            add_meta = source_file.parent.name.startswith("CL")

            if source_file.suffix == '.md':
                from core.convertation_md_to_wp import convert_md_to_blocks
                wp_content = convert_md_to_blocks(content, add_post_meta=add_meta)
            else:
                wp_content = convert_html_to_blocks(content, add_post_meta=add_meta)
            
            if wp_content:
                new_item["wp_block"] = wp_content
                print(f"{display_path}")
            else:
                new_item["wp_block"] = ""
                print(f"Файл {display_path} пуст после конвертации")

        except Exception as e:
            print(f"Ошибка в файле {display_path}: {e}")
            sys.exit(1)

        updated_list.append(new_item)

    print('\n' + '='*50)
    return updated_list
    