<?php

function replace_placeholders_safely($content) {
    if (empty($content) || !is_string($content)) {
        return $content;
    }

    $site_name = get_bloginfo('name');
    $current_date = date_i18n('j F Y');

    $replacements = [
        '$$SITENAME$$'     => $site_name,
        '$$DOMAIN$$'     => $site_name,
        '$$CURRENT_DATE$$' => $current_date,
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

add_filter('the_title', 'replace_placeholders_safely', 20);
add_filter('the_content', 'replace_placeholders_safely', 20);