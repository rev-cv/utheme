import re
from pathlib import Path

from markdown_it import MarkdownIt

from core.wp_html import convert_html_to_blocks

_md = MarkdownIt(options_update={'html': True})

# ─── Множества классов, зеркалящие HTML-конвертер ────────────────────────────

_CALLOUT_CLASSES = {
    'info-callout', 'success-callout', 'warning-callout', 'danger-callout',
    'info-box', 'fun-fact', 'center-box', 'banner-box', 'task-box',
    'callout', 'error-box', 'warning-box', 'success-box',
}

_SECTION_CLASSES = {
    'odds-example', 'key-takeaways', 'worked-example',
    'key-takeaway', 'glossary-term', 'pre-bet-checklist',
}

# ─── Препроцессор ::: блоков ─────────────────────────────────────────────────

def _render_inner(text: str) -> str:
    return _md.render(text.strip())


def _process_fenced_block(header: str, content: str) -> str:
    parts = header.strip().split(None, 1)
    block_type = parts[0]
    block_args = parts[1] if len(parts) > 1 else ''

    if block_type in _CALLOUT_CLASSES | _SECTION_CLASSES:
        return f'<div class="{block_type}">{_render_inner(content)}</div>\n'

    if block_type == 'card-grid':
        cards = re.split(r'\n---\n', content)
        items = ''.join(f'<div>{_render_inner(c)}</div>' for c in cards if c.strip())
        return f'<div class="card-grid">{items}</div>\n'

    if block_type == 'at-a-glance':
        cards = re.split(r'\n---\n', content)
        items = ''.join(f'<div>{_render_inner(c)}</div>' for c in cards if c.strip())
        return f'<div class="at-a-glance">{items}</div>\n'

    if block_type == 'dos-donts':
        halves = re.split(r'\n---\n', content)
        if len(halves) == 2:
            return (
                f'<div class="dos-donts">'
                f'<div>{_render_inner(halves[0])}</div>'
                f'<div>{_render_inner(halves[1])}</div>'
                f'</div>\n'
            )
        return ''

    if block_type == 'details':
        # block_args — текст summary
        return f'<details><summary>{block_args}</summary>{_render_inner(content)}</details>\n'

    if block_type == 'faq':
        # Вопросы разделены через ---
        # Каждый элемент: первая строка = вопрос, остальное = ответ
        items = re.split(r'\n---\n', content)
        tags = []
        for item in items:
            lines = item.strip().split('\n', 1)
            question = lines[0].lstrip('#').strip()
            answer_html = _render_inner(lines[1]) if len(lines) > 1 else ''
            tags.append(f'<details><summary>{question}</summary>{answer_html}</details>')
        return '\n'.join(tags) + '\n'

    if block_type in ('hero-subtitle', 'hero-label'):
        return f'<p class="{block_type}">{content.strip()}</p>\n'

    # Неизвестный тип — оборачиваем как есть, HTML-конвертер проигнорирует
    return f'<div class="{block_type}">{_render_inner(content)}</div>\n'


def _preprocess_fenced_blocks(text: str) -> str:
    """Заменяет ::: ... ::: блоки на HTML до передачи в markdown-it."""
    pattern = re.compile(r'^:::[ ]?(.+?)\n(.*?)^:::[ ]*$', re.MULTILINE | re.DOTALL)
    return pattern.sub(lambda m: _process_fenced_block(m.group(1), m.group(2)), text)


# ─── Фикс изображений: markdown-it оборачивает <img> в <p> ──────────────────

_IMG_IN_P = re.compile(r'<p>(<img\b[^>]*/?>)\s*</p>')

def _promote_images(html: str) -> str:
    return _IMG_IN_P.sub(r'\1', html)


# ─── Публичный API ───────────────────────────────────────────────────────────

def convert_md_to_blocks(md_content: str, add_post_meta: bool = False) -> str:
    """Конвертирует строку Markdown в WP Gutenberg блоки."""
    preprocessed = _preprocess_fenced_blocks(md_content)
    html = _md.render(preprocessed)
    html = _promote_images(html)
    return convert_html_to_blocks(html, add_post_meta=add_post_meta)
