<?php
/**
 * Шаблон карточки статьи: Blurred
 * Размытое фоновое изображение как декор, чёткий thumbnail в углу.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--blurred">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($img_url): ?>
        <div class="blurred-bg" style="background-image: url('<?php echo esc_url($img_url); ?>');" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="blurred-tint" aria-hidden="true"></div>
        <div class="blurred-body">
            <div class="blurred-row">
                <?php if ($img_url): ?>
                <div class="blurred-thumb" style="background-image: url('<?php echo esc_url($img_url); ?>');" aria-hidden="true"></div>
                <?php endif; ?>
                <span class="blurred-date"><?php echo get_the_date(); ?></span>
            </div>
            <h2 class="blurred-title"><?php echo esc_html($title); ?></h2>
            <p class="blurred-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 25); ?></p>
        </div>
    </a>
</div>
