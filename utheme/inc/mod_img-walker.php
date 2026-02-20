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



// Устанавливаем максимальные размеры для среднего размера изображений
update_option( 'thumbnail_size_w', 300 );
update_option( 'thumbnail_size_h', 300 );

// Отключаем жесткую обрезку (crop), чтобы сохранялись пропорции
update_option( 'thumbnail_crop', 0 );


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