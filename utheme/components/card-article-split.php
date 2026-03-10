<?php
/**
 * Шаблон карточки статьи: Split
 * Диагональное разделение между изображением и текстом с анимацией при наведении.
 *
 * @var array $args Аргументы, переданные через get_template_part.
 */

$post_id = get_the_ID();
$permalink = get_permalink($post_id);
$title = get_the_title($post_id);
$img_url = get_the_post_thumbnail_url($post_id, 'medium_large');

?>

<div class="article-card article-card--split">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="card-split__image" <?php if ($img_url) : ?>style="background-image: url('<?php echo esc_url($img_url); ?>');"<?php endif; ?>></div>
        <div class="card-split__overlay">
            <div class="card-split__content">
                <h3><?php echo esc_html($title); ?></h3>
                <p><?php echo wp_trim_words(get_the_excerpt($post_id), 15); ?></p>
                <span class="card-split__read-more"><?php echo get_site_translation('read_more'); ?></span>
            </div>
        </div>
    </a>
</div>