<?php

$read_more_text = isset($args['read_more_text']) ? $args['read_more_text'] : 'Read more';
$post_id = get_the_ID();
$permalink = get_permalink($post_id);
$title = get_the_title($post_id);
$img_url = get_the_post_thumbnail_url($post_id, 'medium_large');

?>

<div class="article-card">
    <picture>
        <img src="<?php echo esc_url($img_url); ?>" loading="lazy" alt="<?php echo esc_html($title); ?>">
    </picture>
    <div class="article-card__body">
        <h3><?php echo esc_html($title); ?></h3>
        <p><?php echo wp_trim_words(get_the_excerpt($post_id), 25); ?></p>
    </div>
    <a href="<?php echo esc_url($permalink); ?>" class="article-card__link"></a>
</div>