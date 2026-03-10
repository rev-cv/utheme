<?php

/* ГЕНЕРАЦИЯ HTML КАРТЫ САЙТА (Аналог Rank Math) */
function get_custom_html_sitemap()
{
    // Получаем ID текущей страницы, чтобы исключить её из списка
    $current_page_id = get_the_ID();

    // Получаем все публичные страницы (для проверки наличия, хотя wp_list_pages сделает это сам)
    $pages = get_pages([
        'sort_column'  => 'menu_order, post_title',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'exclude'      => $current_page_id, // Исключаем текущую страницу
    ]);

    if (empty($pages)) return '';

    $output = '<div class="rank-math-html-sitemap">';
    $output .= '<div class="rank-math-html-sitemap__section rank-math-html-sitemap__section--post-type rank-math-html-sitemap__section--page">';
    
    // Используем кастомную функцию перевода или обычный __()
    $title_text = function_exists('get_site_translation') ? get_site_translation('pages') : __('Pages', 'text-domain');
    $output .= '<h2 class="rank-math-html-sitemap__title">' . $title_text . '</h2>';

    $output .= '<ul class="rank-math-html-sitemap__list">';

    $output .= wp_list_pages([
        'title_li'    => '',
        'echo'        => 0,
        'show_date'   => 'modified',
        'date_format' => '(F j, Y)',
        'exclude'     => $current_page_id, // ОБЯЗАТЕЛЬНО добавляем сюда
        'walker'      => new Custom_Sitemap_Walker()
    ]);

    $output .= '</ul>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

/**
 * КАСТОМНЫЙ WALKER ДЛЯ СТИЛИЗАЦИИ СПИСКА
 * Переделывает стандартный вывод wp_list_pages под структуру Rank Math
 */
class Custom_Sitemap_Walker extends Walker_Page
{
    function start_el(&$output, $page, $depth = 0, $args = [], $current_page = 0)
    {
        $indent = str_repeat("\t", $depth);
        $css_class = 'rank-math-html-sitemap__item';

        $output .= $indent . '<li class="' . $css_class . '">';
        $output .= '<a href="' . get_permalink($page->ID) . '" class="rank-math-html-sitemap__link">' . apply_filters('the_title', $page->post_title, $page->ID) . '</a>';

        if (!empty($args['show_date'])) {
            $date = mysql2date($args['date_format'], $page->post_modified);
            $output .= ' <span class="rank-math-html-sitemap__date">' . $date . '</span>';
        }
    }

    function start_lvl(&$output, $depth = 0, $args = [])
    {
        $output .= '<ul class="rank-math-html-sitemap__list">';
    }
}

add_shortcode('custom_html_sitemap', 'get_custom_html_sitemap');
