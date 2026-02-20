<?php

function related_posts_shortcode()
{
    // Параметры запроса
    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => 4,
        'orderby'        => 'rand', // Случайный порядок
        'post_status'    => 'publish',
        'post__not_in'   => array(get_the_ID()), // Исключаем текущую страницу
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'name',
                'terms'    => array('page+5', 'page+30'),
            ),
        ),
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '';
    }

    ob_start();
?>
    <h3 class="related-title"><?php echo get_site_translation('related_articles'); ?></h3>
    <ul class="related-list">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
            <li>
                <a href="<?php the_permalink(); ?>" class="related-post-link">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="related-thumb">
                            <?php the_post_thumbnail('thumbnail'); ?>
                        </div>
                    <?php endif; ?>
                    <span class="related-post-title"><?php the_title(); ?></span>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
<?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('random_related', 'related_posts_shortcode');
