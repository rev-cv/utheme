<?php

function replace_placeholders_safely($content) {
    if (empty($content) || !is_string($content)) {
        return $content;
    }

    $site_name = get_bloginfo('name');
    $current_date = date_i18n('j F Y');
    $current_year = date('Y');
    $current_date_iso = date('Y-m-d');  // Дата в формате ISO (2026-05-12)

    $replacements = [
        '$$SITENAME$$'     => $site_name,
        '$$SITE_NAME$$'    => $site_name,
        '$$DOMAIN$$'       => $site_name,
        '$$CURRENT_DATE$$' => $current_date,
        '$$CURRENT_DATE_ISO$$' => $current_date_iso,
        '$$CY$$'           => $current_year,
        '$$URL_ABOUT_US$$' => get_about_us_url(),
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

add_filter('the_title', 'replace_placeholders_safely', 20);
add_filter('the_content', 'replace_placeholders_safely', 20);

// Маркер <!-- ut:faq --> нужен только на этапе сборки микроразметки (my_custom_seo_head() читает
// его из сырого post_content до этого фильтра) — в HTML, отдаваемый браузеру, он попадать не должен.
function strip_faq_schema_marker($content) {
    if (empty($content) || !is_string($content)) {
        return $content;
    }
    return str_replace('<!-- ut:faq -->', '', $content);
}
add_filter('the_content', 'strip_faq_schema_marker', 20);

function get_link_shorthand_slug_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = get_transient('link_shorthand_slug_map');
    if ($map === false) {
        $map = [];
        $post_ids = get_posts([
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($post_ids as $post_id) {
            $map[get_post_field('post_name', $post_id)] = get_permalink($post_id);
        }
        set_transient('link_shorthand_slug_map', $map, DAY_IN_SECONDS);
    }

    return $map;
}

function invalidate_link_shorthand_slug_map() {
    delete_transient('link_shorthand_slug_map');
}
add_action('save_post', 'invalidate_link_shorthand_slug_map');
add_action('deleted_post', 'invalidate_link_shorthand_slug_map');
add_action('trashed_post', 'invalidate_link_shorthand_slug_map');
add_action('untrashed_post', 'invalidate_link_shorthand_slug_map');

function handle_custom_link_shorthand($content) {
    if (empty($content) || !is_string($content)) {
        return $content;
    }

    $pattern = '/\$\$LINK\s*([^|]*?)\s*\|\s*(.*?)\$\$/';

    return preg_replace_callback($pattern, function($matches) {
        $slug = sanitize_title(trim($matches[1]));
        $link_text = trim($matches[2]);

        if ($slug === '') {
            return sprintf('<a href="%s">%s</a>', esc_url(home_url('/')), esc_html($link_text));
        }

        $map = get_link_shorthand_slug_map();
        if (isset($map[$slug])) {
            return sprintf('<a href="%s">%s</a>', esc_url($map[$slug]), esc_html($link_text));
        }

        return esc_html($link_text);
    }, $content);
}

add_filter('the_content', 'handle_custom_link_shorthand', 10);

add_action('wp_head', function() {
    $schema = get_post_meta(get_the_ID(), '_schema_html', true);
    if ($schema) echo "\n" . $schema . "\n";
}, 5);