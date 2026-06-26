<?php
/**
 * Шаблон карточки статьи: Overlay
 * Полноразмерное изображение, текст поверх с градиентной подложкой снизу.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--overlay">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="overlay-img"
            <?php if ($img_url): ?>style="background-image: url('<?php echo esc_url($img_url); ?>');"<?php endif; ?>
            aria-hidden="true"></div>
        <div class="overlay-scrim" aria-hidden="true"></div>
        <div class="overlay-body">
            <h2 class="overlay-title"><?php echo esc_html($title); ?></h2>
            <p class="overlay-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 25); ?></p>
        </div>
    </a>
</div>
