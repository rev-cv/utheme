import re
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString
from markdown_it import MarkdownIt

_md = MarkdownIt(options_update={'html': True}).enable('table')

_FRONTMATTER_RE  = re.compile(r'^---\n.*?\n---[ \t]*\n', re.DOTALL)
_TABLE_EM_DASH   = re.compile(r'^(\|[^|\n]*—[^|\n]*\|[^\n]*)$', re.MULTILINE)

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
        return f'<details><summary>{block_args}</summary>{_render_inner(content)}</details>\n'

    if block_type == 'faq':
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

    return f'<div class="{block_type}">{_render_inner(content)}</div>\n'


def _preprocess_fenced_blocks(text: str) -> str:
    pattern = re.compile(r'^:::[ ]?(.+?)\n(.*?)^:::[ ]*$', re.MULTILINE | re.DOTALL)
    return pattern.sub(lambda m: _process_fenced_block(m.group(1), m.group(2)), text)


# ─── Inline rendering ─────────────────────────────────────────────────────────

def _render_inline(inline_token) -> str:
    if not inline_token or not inline_token.children:
        return ''
    return _md.renderer.render(inline_token.children, _md.options, {})


def _process_links(html: str) -> str:
    if '<a ' not in html:
        return html
    soup = BeautifulSoup(f'<span>{html}</span>', 'html.parser')
    for a in soup.find_all('a', href=True):
        href = a['href']
        if href.startswith(('http://', 'https://', 'mailto:', 'tel:', '#', '//', 'javascript:')):
            a['target'] = '_blank'
            a['rel'] = 'noopener'
        else:
            from urllib.parse import urlparse
            path = urlparse(href).path.strip('/')
            if path:
                slug = Path(path.split('/')[-1]).stem
                link_text = a.get_text(strip=True)
                if slug and link_text:
                    a.replace_with(f'$$LINK {slug} | {link_text}$$')
    span = soup.find('span')
    return span.decode_contents() if span else html


# ─── WP block emitters ────────────────────────────────────────────────────────

def _wp_heading(tag: str, content_html: str) -> str:
    level = int(tag[1])
    return (
        f'<!-- wp:heading {{"level":{level}}} -->\n'
        f'<{tag} class="wp-block-heading">{content_html}</{tag}>\n'
        f'<!-- /wp:heading -->'
    )


def _wp_paragraph(content_html: str) -> str:
    return f'<!-- wp:paragraph -->\n<p>{content_html}</p>\n<!-- /wp:paragraph -->'


def _wp_image(img, caption: str = '') -> str:
    src = img.get('src', '')
    alt = img.get('alt', '')
    fname = Path(src).stem + '.webp'
    figcap = f'<figcaption class="wp-element-caption">{caption}</figcaption>' if caption else ''
    return (
        f'<!-- wp:image {{"id":%%IMGID:{fname}%%,"sizeSlug":"full","linkDestination":"none"}} -->\n'
        f'<figure class="wp-block-image size-full">'
        f'<img class="wp-image-%%IMGID:{fname}%%" src="%%IMGSRC:{fname}%%" alt="{alt}"/>'
        f'{figcap}'
        f'</figure>\n'
        f'<!-- /wp:image -->'
    )


def _wp_separator() -> str:
    return '<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->'


def _blocks_from_html(html: str) -> list[str]:
    """Конвертирует HTML-блок (из ::: препроцессора) в WP-блоки."""
    from core.wp_html import BLOCK_HANDLERS
    soup = BeautifulSoup(f'<body>{html.strip()}</body>', 'html.parser')
    result = []
    for child in soup.body.children:
        if isinstance(child, NavigableString):
            continue
        if child.name in BLOCK_HANDLERS:
            result.append(BLOCK_HANDLERS[child.name](child))
    return result


# ─── Token walker ─────────────────────────────────────────────────────────────

def _tokens_to_blocks(tokens: list, add_post_meta: bool = False) -> list[str]:
    blocks: list[str] = []
    i = 0

    while i < len(tokens):
        tok = tokens[i]

        # ── heading ───────────────────────────────────────────────────────────
        if tok.type == 'heading_open':
            inline = tokens[i + 1]
            content = _process_links(_render_inline(inline))
            blocks.append(_wp_heading(tok.tag, content))
            if tok.tag == 'h1' and add_post_meta:
                blocks.append('<!-- wp:shortcode -->[post_meta]<!-- /wp:shortcode -->')
            i += 3

        # ── paragraph ─────────────────────────────────────────────────────────
        elif tok.type == 'paragraph_open':
            inline = tokens[i + 1]
            raw = _render_inline(inline).strip()

            # Solo image → image block; image + *italic* → image with figcaption
            soup = BeautifulSoup(f'<body>{raw}</body>', 'html.parser')
            kids = [c for c in soup.body.children if not isinstance(c, NavigableString)]
            if len(kids) >= 1 and kids[0].name == 'img':
                caption = kids[1].get_text() if len(kids) == 2 and kids[1].name == 'em' else ''
                blocks.append(_wp_image(kids[0], caption))
                i += 3
            else:
                blocks.append(_wp_paragraph(_process_links(raw)))
                i += 3

        # ── lists ─────────────────────────────────────────────────────────────
        elif tok.type in ('bullet_list_open', 'ordered_list_open'):
            close_type = tok.type.replace('_open', '_close')
            depth, j = 1, i + 1
            while j < len(tokens) and depth > 0:
                if tokens[j].type == tok.type:    depth += 1
                elif tokens[j].type == close_type: depth -= 1
                j += 1
            list_html = _md.renderer.render(tokens[i:j], _md.options, {})
            tag = 'ol' if tok.type == 'ordered_list_open' else 'ul'
            soup = BeautifulSoup(f'<body>{list_html}</body>', 'html.parser')
            lst = soup.find(tag)
            if lst:
                ordered_attr = '"ordered":true,' if tag == 'ol' else ''
                blocks.append(
                    f'<!-- wp:list {{{ordered_attr}}} -->\n'
                    f'<{tag}>{lst.decode_contents()}</{tag}>\n'
                    f'<!-- /wp:list -->'
                )
            i = j

        # ── table ─────────────────────────────────────────────────────────────
        elif tok.type == 'table_open':
            depth, j = 1, i + 1
            while j < len(tokens) and depth > 0:
                if tokens[j].type == 'table_open':    depth += 1
                elif tokens[j].type == 'table_close': depth -= 1
                j += 1
            table_html = _md.renderer.render(tokens[i:j], _md.options, {})
            soup = BeautifulSoup(f'<body>{table_html}</body>', 'html.parser')
            tbl = soup.find('table')
            if tbl:
                blocks.append(
                    f'<!-- wp:table -->\n'
                    f'<figure class="wp-block-table">'
                    f'<table class="has-fixed-layout">{tbl.decode_contents()}</table>'
                    f'</figure>\n<!-- /wp:table -->'
                )
            i = j

        # ── html_block (из ::: препроцессора) ─────────────────────────────────
        elif tok.type == 'html_block':
            html_content = tok.content.strip()
            if html_content:
                blocks.extend(_blocks_from_html(html_content))
            i += 1

        # ── thematic break ────────────────────────────────────────────────────
        elif tok.type == 'hr':
            blocks.append(_wp_separator())
            i += 1

        else:
            i += 1

    return blocks


# ─── Публичный API ────────────────────────────────────────────────────────────

def convert_md_to_blocks(md_content: str, add_post_meta: bool = False) -> str:
    """Конвертирует строку Markdown в WP Gutenberg блоки напрямую."""
    md_body = _FRONTMATTER_RE.sub('', md_content, count=1)
    md_body = _TABLE_EM_DASH.sub(lambda m: m.group(1).replace('—', '---'), md_body)
    preprocessed = _preprocess_fenced_blocks(md_body)
    tokens = _md.parse(preprocessed)
    blocks = _tokens_to_blocks(tokens, add_post_meta=add_post_meta)
    return '\n\n'.join(filter(None, blocks))
