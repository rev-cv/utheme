<?php

/**
 * 1. РЕГИСТРАЦИЯ НАСТРОЕК (Settings API)
 * Позволяет управлять крошками через админку, REST API и WP-CLI
 */
add_action('admin_init', 'my_custom_breadcrumbs_settings');
function my_custom_breadcrumbs_settings() {
    register_setting('general', 'bc_enable', [
        'type' => 'boolean',
        'show_in_rest' => true,
        'default' => true,
    ]);
    register_setting('general', 'bc_separator', [
        'type' => 'string',
        'show_in_rest' => true,
        'default' => '|',
    ]);

    add_settings_section('bc_section', 'Breadcrumbs Settings', null, 'general');

    add_settings_field('bc_enable', 'Enable Breadcrumbs', function() {
        $val = get_option('bc_enable', 1);
        echo '<input type="checkbox" name="bc_enable" value="1" ' . checked(1, $val, false) . ' />';
    }, 'general', 'bc_section');

    add_settings_field('bc_separator', 'Separator', function() {
        $val = get_option('bc_separator', '|');
        $options = ['|', '/', '»', '>', '-'];
        echo '<select name="bc_separator">';
        foreach ($options as $opt) {
            echo '<option value="'.esc_attr($opt).'" '.selected($val, $opt, false).'>'.$opt.'</option>';
        }
        echo '</select>';
    }, 'general', 'bc_section');
}

/**
 * 2. ЛОГИКА ПОСТРОЕНИЯ ХЛЕБНЫХ КРОШЕК
 */
function get_my_breadcrumbs() {
    if (!get_option('bc_enable', 1)) return '';

    $sep = '<span class="separator"> ' . esc_html(get_option('bc_separator', '|')) . ' </span>';
    $home_title = get_site_translation('home');
    $before = '<span class="last">';
    $after = '</span>';

    $items = [];
    $items[] = '<a href="' . home_url('/') . '">' . $home_title . '</a>';

    if (is_archive() || is_single()) {
        if (is_single()) {
            $categories = get_the_category();
            if ($categories) {
                $cat = $categories[0];
                $items[] = '<a href="' . get_category_link($cat->term_id) . '">' . $cat->name . '</a>';
            }
            $items[] = $before . get_the_title() . $after;
        } elseif (is_category()) {
            $items[] = $before . single_cat_title('', false) . $after;
        } elseif (is_tag()) {
            $items[] = $before . single_tag_title('', false) . $after;
        }
    } elseif (is_page()) {
        global $post;
        if ($post->post_parent) {
            $parent_id  = $post->post_parent;
            $breadcrumbs = array();
            while ($parent_id) {
                $page = get_page($parent_id);
                $breadcrumbs[] = '<a href="' . get_permalink($page->ID) . '">' . get_the_title($page->ID) . '</a>';
                $parent_id  = $page->post_parent;
            }
            $items = array_merge($items, array_reverse($breadcrumbs));
        }
        $items[] = $before . get_the_title() . $after;
    }

    $output = '<nav aria-label="breadcrumbs" class="rank-math-breadcrumb"><p>';
    $output .= implode($sep, $items);
    $output .= '</p></nav>';

    return $output;
}

/**
 * 3. РЕГИСТРАЦИЯ ШОРТКОДА [my_breadcrumbs]
 */
add_shortcode('my_breadcrumbs', 'get_my_breadcrumbs');




/*

КАК ЭТИМ УПРАВЛЯТЬ?

1. В админке: 
Перейдите в Settings -> General (Настройки -> Общие). 
В самом низу появятся поля для включения и выбора сепаратора.

2. Через WP-CLI:
Включить/выключить: wp option update bc_enable 1
Изменить сепаратор: wp option update bc_separator "»"

3. Через REST API (Application API):
Отправить POST запрос на /wp-json/wp/v2/settings с телом {"bc_enable": false, "bc_separator": "/"}.

4. В шаблоне/теме:
Шорткодом: [my_breadcrumbs]

5. PHP кодом: <?php echo get_my_breadcrumbs(); ?>

*/