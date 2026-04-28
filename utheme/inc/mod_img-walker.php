<?php


/*

class Island_Walker extends Walker_Nav_Menu
{
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $thumbnail_id = get_post_thumbnail_id($item->object_id);
        $img_url = $thumbnail_id ? get_the_post_thumbnail_url($item->object_id, 'thumbnail') : 'https://via.placeholder.com/150x100?text=No+Image';

        $output .= '<li class="menu-item-card">';
        $output .= '<a href="' . $item->url . '">';
        $output .= '<div class="menu-thumb" style="background-image: url(' . $img_url . ');"></div>';
        $output .= '<span class="menu-title">' . $item->title . '</span>';
        $output .= '</a>';
    }
}
*/


class Island_Walker extends Walker_Nav_Menu
{
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $thumbnail_id = get_post_thumbnail_id($item->object_id);
        
        // Используем 'thumbnail' (ваши 150x150), если его нет — заглушка
        $img_url = get_the_post_thumbnail_url($item->object_id, 'thumbnail');

        $output .= '<li class="menu-item-card">';
        $output .= '<a href="' . $item->url . '">';
        $output .= '<div class="menu-thumb">';
        
        // Используем нормальный тег img. 
        // fetchpriority="low" так как картинок в меню может быть много и они не должны мешать основной статье.
        $output .= '<img src="' . $img_url . '" alt="' . esc_attr($item->title) . '" loading="lazy" decoding="async" width="150" height="150">';
        
        $output .= '</div>';
        $output .= '<span class="menu-title">' . $item->title . '</span>';
        $output .= '</a>';
    }
}



/**
 * Walker for the Aside (side-panel) menu — supports 2-level depth.
 * Depth 0: card with thumbnail + title + optional chevron toggle button.
 * Depth 1: compact child links inside collapsible .sub-menu-list.
 */
class Aside_Walker extends Walker_Nav_Menu
{
    function start_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= $depth === 0
            ? '<ul class="sub-menu-list">'
            : '<ul class="sub-sub-menu-list">';
    }

    function end_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= '</ul>';
    }

    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        if ($depth === 0) {
            $has_children = in_array('menu-item-has-children', (array) $item->classes);
            $img_url      = get_the_post_thumbnail_url($item->object_id, 'thumbnail');
            $li_class     = 'menu-item-card' . ($has_children ? ' has-submenu' : '');

            $output .= '<li class="' . $li_class . '">';
            $output .= '<div class="menu-item-row">';
            $output .= '<a href="' . esc_url($item->url) . '">';
            $output .= '<div class="menu-thumb">';
            if ($img_url) {
                $output .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($item->title) . '" loading="lazy" decoding="async" width="150" height="150">';
            }
            $output .= '</div>';
            $output .= '<span class="menu-title">' . esc_html($item->title) . '</span>';
            $output .= '</a>';
            if ($has_children) {
                $output .= '<button class="submenu-toggle" aria-expanded="false" aria-label="Open submenu">';
                $output .= '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
                $output .= '</button>';
            }
            $output .= '</div>';
        } elseif ($depth === 1) {
            $output .= '<li class="sub-menu-item">';
            $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        } else {
            $output .= '<li class="sub-sub-menu-item">';
            $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        }
    }

    function end_el(&$output, $item, $depth = 0, $args = null)
    {
        $output .= '</li>';
    }
}


/**
 * Walker for the Dynamic menu (vertical grid ↔ horizontal row).
 * Outputs a thumbnail circle + label for each nav item.
 */
class Dynamic_Menu_Walker extends Walker_Nav_Menu {
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes   = empty( $item->classes ) ? [] : (array) $item->classes;
        $classes[] = 'dyn-item';
        $class_str = implode( ' ', array_filter( array_unique( $classes ) ) );

        $thumb_url = get_the_post_thumbnail_url( $item->object_id, [ 48, 48 ] );
        $url       = esc_url( $item->url );
        $title     = esc_html( $item->title );

        $output .= '<li class="' . esc_attr( $class_str ) . '">';
        $output .= '<a href="' . $url . '">';

        if ( $thumb_url ) {
            $output .= '<span class="dyn-avatar" style="background-image:url(' . esc_url( $thumb_url ) . ')"></span>';
        } else {
            $initial = esc_html( mb_strtoupper( mb_substr( $item->title, 0, 1 ) ) );
            $output .= '<span class="dyn-avatar dyn-avatar--init">' . $initial . '</span>';
        }

        $output .= '<span class="dyn-label">' . $title . '</span>';
        $output .= '</a>';
    }
}


add_action('after_switch_theme', function () {
    update_option('thumbnail_size_w', 300);
    update_option('thumbnail_size_h', 300);
    update_option('thumbnail_crop', 0);
});


/*
// Разрешаем обновление метаданных через REST API для вложений
add_filter( 'rest_prepare_attachment', function( $response, $post, $request ) {
    $response->header( 'Access-Control-Allow-Methods', 'GET, POST, PATCH' );
    return $response;
}, 10, 3 );

// Регистрируем поле metadata, чтобы оно было доступно для записи
register_meta( 'post', '_wp_attachment_metadata', [
    'type' => 'object',
    'single' => true,
    'show_in_rest' => [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'width'  => [ 'type' => 'integer' ],
                'height' => [ 'type' => 'integer' ],
                'file'   => [ 'type' => 'string' ],
                'sizes'  => [ 'type' => 'object' ],
            ],
        ],
    ],
]);
*/