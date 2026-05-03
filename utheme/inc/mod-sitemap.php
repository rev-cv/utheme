<?php

// Убираем wp-sitemap-users-1.xml
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    if ($name === 'users') return false;
    return $provider;
}, 10, 2);

// Убираем wp-sitemap-taxonomies-category-1.xml
add_filter('wp_sitemaps_taxonomies', function ($taxonomies) {
    unset($taxonomies['category']);
    return $taxonomies;
});






/**
 * Заменяем ссылки вида /category/news/ на /news/ при генерации URL
 */
add_filter('category_link', function ($termlink, $term_id) {
    $term = get_term($term_id);
    if ($term && $term->slug === 'news') {
        return home_url('/news/');
    }
    return $termlink;
}, 10, 2);

add_filter('term_link', function ($termlink, $term, $taxonomy) {
    if ($taxonomy === 'category' && $term->slug === 'news') {
        return home_url('/news/');
    }
    return $termlink;
}, 10, 3);

/**
 * Заменяем ссылки вида /category/event/ на /events/ при генерации URL
 */
add_filter('category_link', function ($termlink, $term_id) {
    $term = get_term($term_id);
    if ($term && $term->slug === 'event') {
        return home_url('/events/');
    }
    return $termlink;
}, 10, 2);

add_filter('term_link', function ($termlink, $term, $taxonomy) {
    if ($taxonomy === 'category' && $term->slug === 'event') {
        return home_url('/events/');
    }
    return $termlink;
}, 10, 3);

/**
 * Редирект с /category/news/ на /news/ (на случай прямых заходов)
 */
add_action('template_redirect', function () {
    if (is_category('news')) {
        wp_safe_redirect(home_url('/news/'), 301);
        exit;
    }
    if (is_category('event')) {
        wp_safe_redirect(home_url('/events/'), 301);
        exit;
    }
});