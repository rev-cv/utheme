<?php
/**
 * Шаблон карточки статьи: Classic
 * Изображение сверху (16:10), заголовок и анонс снизу.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--classic">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="classic-img"
            <?php if ($img_url): ?>style="background-image: url('<?php echo esc_url($img_url); ?>');"<?php endif; ?>
            aria-hidden="true"></div>
        <div class="classic-body">
            <h2 class="classic-title"><?php echo esc_html($title); ?></h2>
            <p class="classic-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 20); ?></p>
        </div>
    </a>
</div>
