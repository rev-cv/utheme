<?php

/**
 * Парсит h2-заголовки из HTML и возвращает готовый HTML блока TOC.
 * Возвращает пустую строку если заголовков нет.
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

function my_theme_dynamic_content_injection($content)
{
    if (!is_single() && !is_page()) return $content;

    // Регулярка захватывает h2 и контент внутри него
    preg_match_all('/<h2.*?>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE);

    if (empty($matches[0])) {
        return $content;
    }

    $toc_items = '';
    $modified_content = $content;
    $offset_shift = 0;
    $first_insertion_pos = null; // Позиция для вставки ТОС и шорткода

    foreach ($matches[0] as $index => $match) {
        $full_tag = $match[0];
        $text_content = strip_tags($matches[1][$index][0]);
        $slug = sanitize_title($text_content) . '-' . $index;

        $original_pos = $match[1];
        $current_pos = $original_pos + $offset_shift;

        $content_before = substr($modified_content, 0, $current_pos);
        $last_section_open = strrpos($content_before, '<section');
        $last_section_close = strrpos($content_before, '</section>');

        $is_inside_section = false;
        $section_pos = -1;

        if ($last_section_open !== false && ($last_section_close === false || $last_section_open > $last_section_close)) {
            $is_inside_section = true;
            $section_pos = $last_section_open;
        }

        if ($is_inside_section) {
            // Удаляем старый ID у секции, если он есть, чтобы не дублировать
            $modified_content = preg_replace('/ id=["\'].*?["\']/i', '', $modified_content, 1, $count);
            $id_attr = ' id="' . $slug . '"';
            $modified_content = substr_replace($modified_content, $id_attr, $section_pos + 8, 0);

            $local_shift = strlen($id_attr);
            $offset_shift += $local_shift;
            $current_pos += $local_shift;
            $target_pos_for_this_item = $section_pos;
        } else {
            // 1. Очищаем старый ID (и с двойными, и с одинарными кавычками)
            $cleaned_h2 = preg_replace('/ id=["\'].*?["\']/i', '', $full_tag);

            // 2. Вставляем новый чистый ID
            $new_h2 = str_replace('<h2', '<h2 id="' . $slug . '"', $cleaned_h2);

            // 3. Заменяем в контенте
            $modified_content = substr_replace($modified_content, $new_h2, $current_pos, strlen($full_tag));

            $local_shift = strlen($new_h2) - strlen($full_tag);
            $offset_shift += $local_shift;
            $target_pos_for_this_item = $current_pos;
        }

        if ($index === 0) {
            $first_insertion_pos = $target_pos_for_this_item;
        }

        $toc_items .= '<li class="page-toc-level-2"><a href="#' . $slug . '" title="move to this section">' . $text_content . '</a></li>';
    }

    $toc_html = build_toc_html($content);

    $shortcode = '[geo_info]';

    // исключаются страницы с категорией Utility Pages
    $excluded_ids = get_posts([
        'post_type'   => 'page',
        'numberposts' => -1,
        'fields'      => 'ids',
        'tax_query'   => [
            [
                'taxonomy' => 'category',
                'field'    => 'name',
                'terms'    => 'Utility Pages',
            ],
        ],
    ]);

    $shortcode_html = '';
    if (!in_array(get_the_ID(), $excluded_ids) && shortcode_exists('geo_info')) {
        $shortcode_html = do_shortcode($shortcode);

        // После do_shortcode() плагин выполнился и заполнил $this->geo_data.
        // Читаем точное число карточек через рефлексию (не трогая плагин),
        // затем заменяем спиннер скелетонами для предотвращения CLS.
        if ($shortcode_html && class_exists('TC_Sports_Predictions_Pro')) {
            $count = 6;
            try {
                $ref = new \ReflectionProperty('TC_Sports_Predictions_Pro', 'geo_data');
                $ref->setAccessible(true);
                $geo = $ref->getValue(TC_Sports_Predictions_Pro::instance());
                if (is_array($geo) && isset($geo['items'])) {
                    $count = max(1, min(count($geo['items']), 20));
                }
            } catch (\ReflectionException $e) { /* fallback to default */ }

            $skeletons = str_repeat('<div class="tc-card-skeleton" aria-hidden="true"></div>', $count);
            $shortcode_html = preg_replace(
                '/<div\s+class="tc-loading">[\s\S]*?<\/p>\s*<\/div>/s',
                $skeletons,
                $shortcode_html,
                1
            );
        }
    }

    $predictions_html = '';
    if (!in_array(get_the_ID(), $excluded_ids) && shortcode_exists('sports_predictions')) {
        $predictions_html = do_shortcode('[sports_predictions]');
    }

    $insertion = '<div class="dynamic-injection-container">' . $shortcode_html . $predictions_html . $toc_html . '</div>';

    if (preg_match('/<p>\s*\[toc_position\]\s*<\/p>/i', $modified_content)) {
        return preg_replace('/<p>\s*\[toc_position\]\s*<\/p>/i', $insertion, $modified_content, 1);
    }
    if (str_contains($modified_content, '[toc_position]')) {
        return str_replace('[toc_position]', $insertion, $modified_content);
    }

    if ($first_insertion_pos !== null) {
        $modified_content = substr_replace($modified_content, $insertion, $first_insertion_pos, 0);
    }

    return $modified_content;
}

add_filter('the_content', 'my_theme_dynamic_content_injection');
