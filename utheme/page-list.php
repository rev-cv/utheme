<?php
/**
 * Template Name: PAGE LIST
 */

get_header();

// 1. Конфигурация
$TEXT_NO_FOUND = get_site_translation('not_found');
$TEXT_READING  = get_site_translation('read_more');
$card_type     = my_theme_get_config('article-card', 'default');

$paged         = (get_query_var('paged')) ? get_query_var('paged') : 1;
$current_slug  = get_post_field('post_name', get_post());

// 2. Логика определения запроса
if ($current_slug === 'news') {
    // Настройки для НОВОСТЕЙ (Записи)
    $args = array(
        'post_type'      => 'post',      // Тянем посты
        'category_name'  => 'news',      // Категория news
        'posts_per_page' => 9,
        'paged'          => $paged,
    );
} else {
    // Настройки для СТАТЕЙ (Страницы)
    $home_id = get_option('page_on_front');
    $args = array(
        'post_type'      => 'page',      // Тянем страницы
        'posts_per_page' => 3,
        'paged'          => $paged,
        'post__not_in'   => array($home_id),
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'name',
                'terms'    => 'Utility Pages',
                'operator' => 'NOT IN',
            ),
        ),
    );
    
    // Если есть ID категории, которую нужно исключить (из твоего шорткода)
    $args['category__not_in'] = array(15);
}

$query = new WP_Query($args);
?>

<main id="site-main" class="site-main">
    <div class="page-list-container">
        
        <header class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
        </header>

        <div class="articles-grid">
            <?php if ($query->have_posts()) : ?>
                
                <?php while ($query->have_posts()) : $query->the_post(); 
                    
                    // Динамический выбор компонента карточки
                    $template_slug = 'components/card';
                    $template_name = 'article-' . $card_type;
                    
                    get_template_part($template_slug, $template_name, ['read_more_text' => $TEXT_READING]);
                    
                endwhile; ?>

                <?php 
                // Пагинация
                $big = 999999999;
                echo '<nav class="grid-pagination">';
                echo paginate_links(array(
                    'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format'    => '?paged=%#%',
                    'current'   => $paged,
                    'total'     => $query->max_num_pages,
                    'prev_text' => '«',
                    'next_text' => '»',
                ));
                echo '</nav>';
                ?>

                <?php wp_reset_postdata(); ?>

            <?php else : ?>
                <p class="not-found"><?php echo $TEXT_NO_FOUND; ?></p>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<?php get_footer(); ?>