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

function handle_custom_link_shorthand($content) {
    if (empty($content) || !is_string($content)) {
        return $content;
    }

    $pattern = '/\$\$LINK\s+([^|]+?)\s*\|\s*(.*?)\$\$/';

    return preg_replace_callback($pattern, function($matches) {
        $slug = sanitize_title(trim($matches[1]));
        $link_text = trim($matches[2]);

        $posts = get_posts([
            'name'           => $slug,
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($posts)) {
            $url = get_permalink($posts[0]);
            return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($link_text));
        }

        return esc_html($link_text);
    }, $content);
}

add_filter('the_content', 'handle_custom_link_shorthand', 10);

add_action('wp_head', function() {
    $schema = get_post_meta(get_the_ID(), '_schema_html', true);
    if ($schema) echo "\n" . $schema . "\n";
}, 5);