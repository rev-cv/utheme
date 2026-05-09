<?php
/**
 * Шаблон карточки статьи: Editorial
 * Редакционный стиль: верхняя граница, thumbnail, заголовок, анонс, футер.
 */

$post_id   = get_the_ID();
$permalink = get_permalink($post_id);
$title     = get_the_title($post_id);
$img_url   = get_the_post_thumbnail_url($post_id, 'medium_large');
?>

<div class="article-card article-card--editorial">
    <a href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="editorial-head">
            <span class="editorial-date"><?php echo get_the_date(); ?></span>
            <?php if ($img_url): ?>
            <div class="editorial-thumb" style="background-image: url('<?php echo esc_url($img_url); ?>');" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <h2 class="editorial-title"><?php echo esc_html($title); ?></h2>
        <p class="editorial-desc"><?php echo wp_trim_words(get_the_excerpt($post_id), 25); ?></p>
        <div class="editorial-foot">
            <span class="editorial-cta"><?php echo get_site_translation('read_more'); ?> →</span>
        </div>
    </a>
</div>
