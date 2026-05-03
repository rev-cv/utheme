<?php

/**
 * ЛОГИКА ПОСТРОЕНИЯ ХЛЕБНЫХ КРОШЕК
 */

/**
 * Gets breadcrumb items as a structured array.
 * This separates data logic from HTML presentation and can be reused for schema.
 *
 * @return array An array of breadcrumb items, each with 'name' and 'url'.
 */
function get_my_breadcrumbs_items() {
    $items = [];

    $home_title = function_exists('get_site_translation') ? get_site_translation('home') : 'Home';
    $items[] = [
        'name' => $home_title,
        'url'  => home_url('/'),
    ];

    if (is_front_page()) {
        return $items;
    }

    if (is_home() && get_option('show_on_front') === 'page') { // Blog page
        $items[] = [
            'name' => get_the_title(get_option('page_for_posts')),
            'url'  => get_permalink(get_option('page_for_posts')),
        ];
    } elseif (is_single()) { // For posts
        $categories = get_the_category();
        if ($categories) {
            $cat = $categories[0];
            $items[] = [
                'name' => $cat->name,
                'url'  => get_category_link($cat->term_id),
            ];
        }
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_page()) { // For pages
        global $post;
        if ($post->post_parent) {
            $parent_id  = $post->post_parent;
            $breadcrumbs = [];
            while ($parent_id) {
                $page = get_page($parent_id);
                $breadcrumbs[] = [
                    'name' => get_the_title($page->ID),
                    'url'  => get_permalink($page->ID),
                ];
                $parent_id  = $page->post_parent;
            }
            $items = array_merge($items, array_reverse($breadcrumbs));
        }
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_category()) {
        $items[] = [
            'name' => single_cat_title('', false),
            'url'  => get_category_link(get_queried_object_id()),
        ];
    } elseif (is_tag()) {
        $items[] = [
            'name' => single_tag_title('', false),
            'url'  => get_tag_link(get_queried_object_id()),
        ];
    }

    return $items;
}

function get_my_breadcrumbs() {
    $breadcrumb_items = get_my_breadcrumbs_items();

    // Don't show breadcrumbs if there's only one item (e.g., just "Home").
    if (count($breadcrumb_items) <= 1) {
        return '';
    }

    $sep = '<span class="separator"> ' . esc_html(my_theme_get_config('breadcrumbs-separator', '/')) . ' </span>';
    $before = '<span class="last">';
    $after = '</span>';

    $html_items = [];
    $total_items = count($breadcrumb_items);

    foreach ($breadcrumb_items as $i => $item) {
        if ($i === $total_items - 1) {
            $html_items[] = $before . esc_html($item['name']) . $after;
        } else {
            $html_items[] = '<a href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . '</a>';
        }
    }

    $output = '<nav aria-label="breadcrumbs" class="rank-math-breadcrumb"><p>';
    $output .= implode($sep, $html_items);
    $output .= '</p></nav>';

    return $output;
}

/**
 * 3. РЕГИСТРАЦИЯ ШОРТКОДА [my_breadcrumbs]
 */
add_shortcode('my_breadcrumbs', 'get_my_breadcrumbs');




/*

Использование:
- Шорткодом: [my_breadcrumbs]
- PHP: <?php echo get_my_breadcrumbs(); ?>
- Изменить сепаратор: wp option update bc_separator "»"

*/