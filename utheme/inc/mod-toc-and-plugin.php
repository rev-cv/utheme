<?php

/**
 * Динамическая вставка TOC и рекламных блоков в контент страницы.
 *
 * Ручное позиционирование — вставьте любой из тегов прямо в редакторе страницы:
 *   [toc_position]   — TOC (по умолчанию: перед первым h2)
 *   [geo_position]   — блок geo_info (по умолчанию: авто, см. ниже)
 *   [sport_position] — блок sports_predictions (по умолчанию: авто, см. ниже)
 *
 * Авто-позиционирование рекламных блоков (если ручной тег не задан):
 *   — перед первой картинкой, если она стоит до первого h2
 *   — иначе после h1
 *
 * Блоки независимы: можно задать вручную только один из двух рекламных тегов,
 * второй встанет по авто-логике.
 */

/**
 * Генерирует CSS-safe якорь для TOC.
 * sanitize_title() на кириллице может вернуть percent-encoded slug (%d0...),
 * который ломает document.querySelector('#%d0...') в JS.
 */
function my_theme_toc_anchor_id(string $text, int $index): string
{
    $base = sanitize_title($text);

    // Для кириллицы/не-ASCII WordPress часто возвращает %d0%... — делаем стабильный ASCII fallback.
    if ($base === '' || preg_match('/%[0-9a-f]{2}/i', $base)) {
        $base = substr(md5($text), 0, 10);
    }

    // Дополнительная защита: только символы, безопасные для CSS-селектора и HTML id.
    $base = preg_replace('/[^a-z0-9_-]+/i', '-', $base);
    $base = trim(preg_replace('/-+/', '-', $base), '-_');

    if ($base === '') {
        $base = substr(md5($text), 0, 10);
    }

    // Префикс нужен, чтобы id не начинался с цифры.
    return 'section-' . $base . '-' . $index;
}

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
        $slug = my_theme_toc_anchor_id($text_content, $index);
        $toc_items .= '<li class="page-toc-level-2"><a href="#' . esc_attr($slug) . '" title="move to this section">' . esc_html($text_content) . '</a></li>';
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
    $first_insertion_pos = null; // Позиция для вставки TOC

    foreach ($matches[0] as $index => $match) {
        $full_tag = $match[0];
        $text_content = strip_tags($matches[1][$index][0]);
        $slug = my_theme_toc_anchor_id($text_content, $index);

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
            // Меняем id только у текущего открывающего <section>, не у первого id во всём контенте.
            $section_open_end = strpos($modified_content, '>', $section_pos);

            if ($section_open_end !== false) {
                $section_open_tag = substr($modified_content, $section_pos, $section_open_end - $section_pos + 1);
                $clean_section_open_tag = preg_replace('/\s+id=(["\']).*?\1/i', '', $section_open_tag);
                $new_section_open_tag = preg_replace(
                    '/^<section\b/i',
                    '<section id="' . esc_attr($slug) . '"',
                    $clean_section_open_tag,
                    1
                );

                $modified_content = substr_replace(
                    $modified_content,
                    $new_section_open_tag,
                    $section_pos,
                    strlen($section_open_tag)
                );

                $local_shift = strlen($new_section_open_tag) - strlen($section_open_tag);
                $offset_shift += $local_shift;
                $current_pos += $local_shift;
            }

            $target_pos_for_this_item = $section_pos;
        } else {
            // 1. Очищаем старый ID (и с двойными, и с одинарными кавычками)
            $cleaned_h2 = preg_replace('/ id=["\'].*?["\']/i', '', $full_tag);

            // 2. Вставляем новый чистый ID
            $new_h2 = str_replace('<h2', '<h2 id="' . esc_attr($slug) . '"', $cleaned_h2);

            // 3. Заменяем в контенте
            $modified_content = substr_replace($modified_content, $new_h2, $current_pos, strlen($full_tag));

            $local_shift = strlen($new_h2) - strlen($full_tag);
            $offset_shift += $local_shift;
            $target_pos_for_this_item = $current_pos;
        }

        if ($index === 0) {
            $first_insertion_pos = $target_pos_for_this_item;
        }

        $toc_items .= '<li class="page-toc-level-2"><a href="#' . esc_attr($slug) . '" title="move to this section">' . esc_html($text_content) . '</a></li>';
    }

    // — TOC — вставляется перед первым h2 (или в [toc_position]) —
    $toc_html  = build_toc_html($content);
    $toc_block = $toc_html ? '<div class="dynamic-injection-container">' . $toc_html . '</div>' : '';

    if ($toc_block) {
        if (preg_match('/<p>\s*\[toc_position\]\s*<\/p>/i', $modified_content)) {
            $modified_content = preg_replace('/<p>\s*\[toc_position\]\s*<\/p>/i', $toc_block, $modified_content, 1);
        } elseif (str_contains($modified_content, '[toc_position]')) {
            $modified_content = str_replace('[toc_position]', $toc_block, $modified_content);
        } elseif ($first_insertion_pos !== null) {
            $modified_content = substr_replace($modified_content, $toc_block, $first_insertion_pos, 0);
        }
    }

    // — Рекламные блоки — вставляются перед первой картинкой или после h1 —

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
        $shortcode_html = do_shortcode('[geo_info]');

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

    // Ручное позиционирование: [geo_position] и [sport_position]
    $geo_placed   = false;
    $sport_placed = false;

    if ($shortcode_html) {
        $geo_wrap = '<div class="dynamic-injection-container">' . $shortcode_html . '</div>';
        if (preg_match('/<p>\s*\[geo_position\]\s*<\/p>/i', $modified_content)) {
            $modified_content = preg_replace('/<p>\s*\[geo_position\]\s*<\/p>/i', $geo_wrap, $modified_content, 1);
            $geo_placed = true;
        } elseif (str_contains($modified_content, '[geo_position]')) {
            $modified_content = str_replace('[geo_position]', $geo_wrap, $modified_content);
            $geo_placed = true;
        }
    }

    if ($predictions_html) {
        $sport_wrap = '<div class="dynamic-injection-container">' . $predictions_html . '</div>';
        if (preg_match('/<p>\s*\[sport_position\]\s*<\/p>/i', $modified_content)) {
            $modified_content = preg_replace('/<p>\s*\[sport_position\]\s*<\/p>/i', $sport_wrap, $modified_content, 1);
            $sport_placed = true;
        } elseif (str_contains($modified_content, '[sport_position]')) {
            $modified_content = str_replace('[sport_position]', $sport_wrap, $modified_content);
            $sport_placed = true;
        }
    }

    // Авто-позиционирование того, что не было размещено вручную
    $auto_geo   = $geo_placed   ? '' : $shortcode_html;
    $auto_sport = $sport_placed ? '' : $predictions_html;

    $ads_block = '';
    if ($auto_geo || $auto_sport) {
        $ads_block = '<div class="dynamic-injection-container">' . $auto_geo . $auto_sport . '</div>';
    }

    if ($ads_block) {
        // Ищем позиции в уже модифицированном контенте (после вставки TOC)
        preg_match('/<img[\s>]/i', $modified_content, $img_m, PREG_OFFSET_CAPTURE);
        preg_match('/<h2[\s>]/i', $modified_content, $h2_m,  PREG_OFFSET_CAPTURE);

        $first_img_pos  = $img_m ? $img_m[0][1] : false;
        $first_h2_pos_m = $h2_m  ? $h2_m[0][1]  : false;

        // Картинка существует и стоит до первого h2
        $img_before_h2 = $first_img_pos !== false
            && ($first_h2_pos_m === false || $first_img_pos < $first_h2_pos_m);

        if ($img_before_h2) {
            // Если img обёрнут в figure — вставляем перед figure, а не внутрь него
            $before_img        = substr($modified_content, 0, $first_img_pos);
            $last_figure_open  = strrpos($before_img, '<figure');
            $last_figure_close = strrpos($before_img, '</figure>');
            $inside_figure     = $last_figure_open !== false
                && ($last_figure_close === false || $last_figure_open > $last_figure_close);
            $insert_pos = $inside_figure ? $last_figure_open : $first_img_pos;

            $modified_content = substr_replace($modified_content, $ads_block, $insert_pos, 0);
        } elseif (preg_match('/<\/h1>/i', $modified_content, $h1_m, PREG_OFFSET_CAPTURE)) {
            $after_h1 = $h1_m[0][1] + strlen($h1_m[0][0]);
            $modified_content = substr_replace($modified_content, $ads_block, $after_h1, 0);
        }
    }

    return $modified_content;
}

add_filter('the_content', 'my_theme_dynamic_content_injection');
