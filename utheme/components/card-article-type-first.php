<?php
/**
 * Шаблон карточки статьи: Type-first
 * Заголовок — главный герой, изображение — маленький stamp в строке.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--type-first">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <h2 class="type-first-title">
            <?php echo esc_html($title); ?>
            <?php if ($img_url): ?>
            <span class="type-first-stamp" style="background-image: url('<?php echo esc_url($img_url); ?>');" aria-hidden="true"></span>
            <?php endif; ?>
        </h2>
        <p class="type-first-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 20); ?></p>
    </a>
</div>
