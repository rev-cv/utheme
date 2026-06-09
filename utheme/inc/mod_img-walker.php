<?php

class Island_Walker extends Walker_Nav_Menu
{
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $img_url = get_the_post_thumbnail_url($item->object_id, 'thumbnail');

        $output .= '<li class="ut-item">';
        $output .= '<a href="' . esc_url($item->url) . '">';
        $output .= '<div class="ut-item__thumb">';
        $output .= '<img src="' . $img_url . '" alt="' . esc_attr($item->title) . '" loading="lazy" decoding="async" width="150" height="150">';
        $output .= '</div>';
        $output .= '<span class="ut-item__label">' . $item->title . '</span>';
        $output .= '</a>';
    }
}

/**
 * Walker for the Aside (side-panel) menu — supports 3-level depth.
 * Depth 0: card with thumbnail + title + optional expand toggle button.
 * Depth 1: compact child links inside collapsible ut-item__sub.
 * Depth 2: nested links inside ut-item__sub-sub.
 */
class Aside_Walker extends Walker_Nav_Menu
{
    function start_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= $depth === 0
            ? '<ul class="ut-item__sub">'
            : '<ul class="ut-item__sub-sub">';
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
            $li_class     = 'ut-item' . ($has_children ? ' ut-item--has-sub' : '');

            $output .= '<li class="' . $li_class . '">';
            $output .= '<div class="ut-item__row">';
            $output .= '<a href="' . esc_url($item->url) . '">';
            $output .= '<div class="ut-item__thumb">';
            if ($img_url) {
                $output .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($item->title) . '" loading="lazy" decoding="async" width="150" height="150">';
            }
            $output .= '</div>';
            $output .= '<span class="ut-item__label">' . esc_html($item->title) . '</span>';
            $output .= '</a>';
            if ($has_children) {
                $output .= '<button class="ut-item__toggle" aria-expanded="false" aria-label="Open submenu">';
                $output .= '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
                $output .= '</button>';
            }
            $output .= '</div>';
        } elseif ($depth === 1) {
            $output .= '<li class="ut-item__sub-item">';
            $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        } else {
            $output .= '<li class="ut-item__sub-sub-item">';
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
        $classes[] = 'ut-dyn__item';
        $class_str = implode( ' ', array_filter( array_unique( $classes ) ) );

        $thumb_url = get_the_post_thumbnail_url( $item->object_id, [ 48, 48 ] );
        $url       = esc_url( $item->url );
        $title     = esc_html( $item->title );

        $output .= '<li class="' . esc_attr( $class_str ) . '">';
        $output .= '<a href="' . $url . '">';

        if ( $thumb_url ) {
            $output .= '<span class="ut-dyn__avatar" style="background-image:url(' . esc_url( $thumb_url ) . ')"></span>';
        } else {
            $initial = esc_html( mb_strtoupper( mb_substr( $item->title, 0, 1 ) ) );
            $output .= '<span class="ut-dyn__avatar ut-dyn__avatar--init">' . $initial . '</span>';
        }

        $output .= '<span class="ut-dyn__label">' . $title . '</span>';
        $output .= '</a>';
    }
}


add_action('after_switch_theme', function () {
    update_option('thumbnail_size_w', 300);
    update_option('thumbnail_size_h', 300);
    update_option('thumbnail_crop', 0);
});
