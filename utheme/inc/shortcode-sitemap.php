<?php

/* ГЕНЕРАЦИЯ HTML КАРТЫ САЙТА (Аналог Rank Math) */
function get_custom_html_sitemap()
{
    // Получаем все публичные страницы
    $pages = get_pages([
        'sort_column'  => 'menu_order, post_title',
        'post_type'    => 'page',
        'post_status'  => 'publish',
    ]);

    if (empty($pages)) return '';

    $output = '<div class="rank-math-html-sitemap">';
    $output .= '<div class="rank-math-html-sitemap__section rank-math-html-sitemap__section--post-type rank-math-html-sitemap__section--page">';
    $output .= '<h2 class="rank-math-html-sitemap__title">' . get_site_translation('pages') . '</h2>';

    $output .= '<ul class="rank-math-html-sitemap__list">';

    // Используем встроенную функцию WP для построения иерархического списка, 
    // но через фильтр приведем её к нужному формату HTML
    $output .= wp_list_pages([
        'title_li'    => '',
        'echo'        => 0,
        'show_date'   => 'modified',
        'date_format' => '(F j, Y)',
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
