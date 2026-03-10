<?php
/**
 * Шаблон карточки статьи: Float
 * Заголовок "плавает" поверх изображения.
 *
 * @var array $args Аргументы, переданные через get_template_part.
 */

$post_id = get_the_ID();
$permalink = get_permalink($post_id);
$title = get_the_title($post_id);
$img_url = get_the_post_thumbnail_url($post_id, 'medium_large');

?>

<div class="article-card">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <picture>
            <?php if ($img_url) : ?>
                <img src="<?php echo esc_url($img_url); ?>" loading="lazy" alt="">
            <?php endif; ?>
        </picture>
        <h3><?php echo esc_html($title); ?></h3>
        <p><?php echo wp_trim_words(get_the_excerpt($post_id), 15); ?></p>
    </a>
</div>