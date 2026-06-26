<?php
/**
 * Шаблон карточки статьи: Clipped
 * Изображение обтекается текстом через float + shape-outside.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--clipped">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($img_url): ?>
        <div class="clipped-img" style="background-image: url('<?php echo esc_url($img_url); ?>');" aria-hidden="true"></div>
        <?php endif; ?>
        <h2 class="clipped-title"><?php echo esc_html($title); ?></h2>
        <p class="clipped-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 25); ?></p>
        <span class="clipped-cta"><?php echo get_site_translation('read_more'); ?> ↗</span>
    </a>
</div>
