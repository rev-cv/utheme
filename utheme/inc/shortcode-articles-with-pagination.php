<?php

function custom_articles_shortcode()
{
    $TEXT_NO_FOUND = get_site_translation('not_found');
    $TEXT_READING = get_site_translation('read_more');

    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $home_id = get_option('page_on_front');

    $args = array(
        'post_type' => 'page',
        'posts_per_page' => 9, // Оптимально для сетки по 3 в ряд
        'paged' => $paged,
        'post__not_in' => array($home_id),
        'category__not_in' => array(15),
        'tax_query' => array(
            array(
                'taxonomy' => 'category',
                'field' => 'name',
                'terms' => 'Utility Pages',
                'operator' => 'NOT IN',
            ),
        ),
    );

    $query = new WP_Query($args);
    $output = '<div class="articles-grid">';

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $img_url = get_the_post_thumbnail_url(get_the_ID(), 'medium_large');

            $output .= '<div class="article-card">';

            // Вывод картинки
            if ($img_url) {
                $output .= '<div class="card-image" style="background-image: url(' . esc_url($img_url) . ');"></div>';
            } else {
                $output .= '<div class="card-image no-image"></div>'; // Заглушка
            }

            $output .= '<div class="card-content">';
            $output .= '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            $output .= '<p>' . wp_trim_words(get_the_excerpt(), 15) . '</p>';
            $output .= '<a class="read-more" href="' . get_permalink() . '">' . $TEXT_READING . '</a>';
            $output .= '</div>';
            $output .= '</div>';
        endwhile;

        // Пагинация
        $big = 999999999;
        $pagination = paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => $paged,
            'total' => $query->max_num_pages,
            'prev_text' => '«',
            'next_text' => '»',
        ));

        if ($pagination) {
            $output .= '<nav class="grid-pagination">' . $pagination . '</nav>';
        }

        wp_reset_postdata();
    else :
        $output .= '<p>' . $TEXT_NO_FOUND . '</p>';
    endif;

    $output .= '</div>';

    return $output;
}

add_shortcode('articles_with_pagination', 'custom_articles_shortcode');
