<?php
/**
 * Шаблон карточки статьи.
 *
 * @var array $args Аргументы, переданные через get_template_part (доступно с WP 5.5).
 */

$read_more_text = isset($args['read_more_text']) ? $args['read_more_text'] : 'Read more';
$post_id = get_the_ID();
$permalink = get_permalink($post_id);
$title = get_the_title($post_id);
$img_url = get_the_post_thumbnail_url($post_id, 'medium_large');
?>
<div class="article-card">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($img_url) : ?>
            <div class="card-image" style="background-image: url(<?php echo esc_url($img_url); ?>);"></div>
        <?php else : ?>
            <div class="card-image no-image"></div>
        <?php endif; ?>
    </a>
    <div class="card-content">
        <h3><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
        <p><?php echo wp_trim_words(get_the_excerpt($post_id), 15); ?></p>
        <a class="read-more" href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($read_more_text); ?></a>
    </div>
</div>