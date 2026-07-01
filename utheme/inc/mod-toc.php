<?php

/**
 * Динамическая вставка TOC в контент страницы.
 *
 * Ручное позиционирование — вставьте тег прямо в редакторе страницы:
 *   [toc_position] — TOC (по умолчанию: перед первым h2)
 */

function build_toc_html(string $content): string
{
    preg_match_all('/<h2.*?>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE);

    if (empty($matches[0])) return '';

    $toc_title = get_site_translation('toc');
    $toc_items = '';

    foreach ($matches[0] as $index => $match) {
        $text_content = strip_tags($matches[1][$index][0]);
        $slug = sanitize_title($text_content) . '-' . $index;
        $toc_items .= '<li class="page-toc-level-2"><a href="#' . $slug . '" title="move to this section">' . esc_html($text_content) . '</a></li>';
    }

    $toc_tag      = my_theme_get_config('is-not-section', false) ? 'div' : 'section';
    $collapsible  = my_theme_get_config('toc-collapsible', false);
    $show_title   = my_theme_get_config('toc-show-title', true);
    $toc_menu     = my_theme_get_config('toc-menu', 'icon');

    $can_collapse = $collapsible && $show_title && $toc_menu !== 'tags';
    $list_html    = '<ol class="page-toc-list">' . $toc_items . '</ol>';

    if ($can_collapse) {
        $inner = '<details>'
               . '<summary class="page-toc-title">' . $toc_title . '</summary>'
               . $list_html
               . '</details>';
    } else {
        $inner = '<div class="page-toc-title">' . $toc_title . '</div>' . $list_html;
    }

    return '<' . $toc_tag . ' class="toc">' . $inner . '</' . $toc_tag . '>';
}

function my_theme_toc_injection($content)
{
    if (!is_single() && !is_page()) return $content;

    preg_match_all('/<h2.*?>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE);

    if (empty($matches[0])) return $content;

    $modified_content    = $content;
    $offset_shift        = 0;
    $first_insertion_pos = null;

    foreach ($matches[0] as $index => $match) {
        $full_tag     = $match[0];
        $text_content = strip_tags($matches[1][$index][0]);
        $slug         = sanitize_title($text_content) . '-' . $index;

        $original_pos = $match[1];
        $current_pos  = $original_pos + $offset_shift;

        $content_before     = substr($modified_content, 0, $current_pos);
        $last_section_open  = strrpos($content_before, '<section');
        $last_section_close = strrpos($content_before, '</section>');

        $is_inside_section = $last_section_open !== false
            && ($last_section_close === false || $last_section_open > $last_section_close);

        if ($is_inside_section) {
            $modified_content = preg_replace('/ id=["\'].*?["\']/i', '', $modified_content, 1);
            $id_attr          = ' id="' . $slug . '"';
            $modified_content = substr_replace($modified_content, $id_attr, $last_section_open + 8, 0);

            $offset_shift           += strlen($id_attr);
            $current_pos            += strlen($id_attr);
            $target_pos_for_this_item = $last_section_open;
        } else {
            $cleaned_h2 = preg_replace('/ id=["\'].*?["\']/i', '', $full_tag);
            $new_h2     = str_replace('<h2', '<h2 id="' . $slug . '"', $cleaned_h2);

            $modified_content = substr_replace($modified_content, $new_h2, $current_pos, strlen($full_tag));

            $offset_shift           += strlen($new_h2) - strlen($full_tag);
            $target_pos_for_this_item = $current_pos;
        }

        if ($index === 0) {
            $first_insertion_pos = $target_pos_for_this_item;
        }
    }

    $toc_html = build_toc_html($content);

    if ($toc_html) {
        if (preg_match('/<p>\s*\[toc_position\]\s*<\/p>/i', $modified_content)) {
            $modified_content = preg_replace('/<p>\s*\[toc_position\]\s*<\/p>/i', $toc_html, $modified_content, 1);
        } elseif (str_contains($modified_content, '[toc_position]')) {
            $modified_content = str_replace('[toc_position]', $toc_html, $modified_content);
        } elseif ($first_insertion_pos !== null) {
            $modified_content = substr_replace($modified_content, $toc_html, $first_insertion_pos, 0);
        }
    }

    return $modified_content;
}

add_filter('the_content', 'my_theme_toc_injection', 10);
