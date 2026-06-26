<?php

add_filter('the_content', function ($content) {
    if (!is_single() && !is_page()) return $content;

    // Если страница имеет категорию "Utility Pages", ничего не добавляем
    if (has_category('Utility Pages')) {
        return $content;
    }
    if (!has_about_us_page()) {
        return $content;
    }
    return $content . '<p>' .  get_site_translation('created_by_editorial') . ".</p>";
}, 30);
