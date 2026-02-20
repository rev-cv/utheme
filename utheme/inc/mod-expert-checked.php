<?php

add_filter('the_content', function ($content) {
    if (!is_single() && !is_page()) return $content;

    // Если страница имеет категорию "Utility Pages", ничего не добавляем
    if (has_category('Utility Pages')) {
        return $content;
    }

    $label = get_site_translation('expert_checked');

    global $post;
    $author_id = $post->post_author;

    $first_name = get_the_author_meta('first_name', $author_id);
    $last_name = get_the_author_meta('last_name', $author_id);
    $author = trim($first_name . ' ' . $last_name);

    // Если имя и фамилия не заполнены, используем стандартное отображаемое имя
    if (empty($author)) {
        $author = get_the_author_meta('display_name', $author_id);
    }

    $content .= '<p><strong>' . $label . ':</strong> ' . $author . '</p>';

    return $content;
}, 30);
