<?php

function my_theme_dynamic_content_injection($content)
{
    if (!is_single() && !is_page()) return $content;

    $toc_title = get_site_translation('toc');

    // Регулярка захватывает h2 и контент внутри него
    preg_match_all('/<h2.*?>(.*?)<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE);

    if (empty($matches[0]) || in_array(get_the_ID(), [15])) {
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
            // (Внимание: прямая вставка по смещению в секцию сложна, упростим до добавления ID к заголовку, 
            // но если нужна именно секция, то логика ниже)
            $id_attr = ' id="' . $slug . '"';
            $modified_content = substr_replace($modified_content, $id_attr, $section_pos + 8, 0);
            
            $local_shift = strlen($id_attr);
            $offset_shift += $local_shift;
            $current_pos += $local_shift;
            $target_pos_for_this_item = $section_pos;
        } else {
            // Исправленная логика для H2:
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

    // --- ГЕНЕРАЦИЯ И ВСТАВКА TOC ---
    $conf_file = get_template_directory() . '/src/conf.scss';
    $toc_tag = 'section';

    if (file_exists($conf_file)) {
        $conf_content = file_get_contents($conf_file);
        if (preg_match('/\$is-not-section:\s*["\']?(true|false)["\']?/', $conf_content, $matches)) {
            if ($matches[1] === 'true') {
                $toc_tag = 'div';
            }
        }
    }

    $toc_html = '<' . $toc_tag . ' class="toc">
                    <div class="page-toc-title">' . $toc_title . '</div>
                    <ol class="page-toc-list">' . $toc_items . '</ol>
                 </' . $toc_tag . '>';

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

    if ($first_insertion_pos !== null) {
        $shortcode_html = '';
        // Добавляем шорткод только если страница не исключена
        if (in_array(get_the_ID(), $excluded_ids)) {
            // для исключенных страниц шорткод не нужен
        } else {
            // Проверяем наличие шорткода (плагин Sports Predictions)
            if (shortcode_exists('geo_info')) {
                $shortcode_html = do_shortcode($shortcode);
            }
        }

        // Оборачиваем и шорткод (если он есть), и TOC в один контейнер.
        // Это делает вставку единым, цельным блоком, что более предсказуемо для верстки и предотвращает "разъезжание" элементов.
        $insertion = '<div class="dynamic-injection-container">' . $shortcode_html . $toc_html . '</div>';
        $insertion = '' . $shortcode_html . $toc_html . '';

        $modified_content = substr_replace($modified_content, $insertion, $first_insertion_pos, 0);
    }

    return $modified_content;
}

add_filter('the_content', 'my_theme_dynamic_content_injection');
