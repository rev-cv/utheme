<?php

function custom_articles_shortcode()
{
    $TEXT_NO_FOUND = get_site_translation('not_found');
    $TEXT_READING = get_site_translation('read_more');

    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $home_id = get_option('page_on_front');

    $args = array(
        'post_type' => 'page',
        'posts_per_page' => 9, // оптимально для сетки по 3 в ряд
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
            ob_start();
            $card_type = my_theme_get_config('article-card', 'default');

            // подключение шаблона components/card-article.php
            if ($card_type === 'default') {
                get_template_part('components/card', 'article-default', ['read_more_text' => $TEXT_READING]);
            }
            elseif ($card_type === 'frame') {
                get_template_part('components/card', 'article-frame', ['read_more_text' => $TEXT_READING]);
            }
            elseif ($card_type === 'slide') {
                get_template_part('components/card', 'article-slide', ['read_more_text' => $TEXT_READING]);
            }
            elseif ($card_type === 'windows') {
                get_template_part('components/card', 'article-windows', ['read_more_text' => $TEXT_READING]);
            }

            $output .= ob_get_clean();
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
