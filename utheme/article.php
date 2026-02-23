<?php /* Template Name: ARTICLE */ ?>
<?php get_header() ?>

<?php while (have_posts()) : the_post(); ?>
    <?php
    // временно отключаем инъекцию TOC, чтобы она не мешала парсингу DOM
    remove_filter('the_content', 'my_theme_dynamic_content_injection');

    $content = get_the_content();
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);

    $extracted = extract_header_box_from_html($content);

    // вручную применяем инъекцию только к основному контенту (уже без хедера)
    if (function_exists('my_theme_dynamic_content_injection')) {
        $extracted['content'] = my_theme_dynamic_content_injection($extracted['content']);
    }

    // возвращаем фильтр на место для других циклов
    add_filter('the_content', 'my_theme_dynamic_content_injection');
    ?>
    <?php echo $extracted['header']; ?>
    <main>
        <article class="article">
            <?php if (!is_front_page()) echo get_my_breadcrumbs(); ?>
            <?php echo $extracted['content']; ?>
        </article>
    </main>
<?php endwhile; ?>

<?php
get_footer();