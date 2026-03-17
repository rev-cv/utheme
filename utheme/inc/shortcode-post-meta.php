<?php

function get_reading_time($post_id = null)
{
    $post = get_post($post_id);

    if (!$post || empty($post->post_content)) {
        return 0;
    }

    $content = strip_tags($post->post_content);

    $word_count = str_word_count($content);

    $minutes = ceil($word_count / 180);

    return $minutes;
}

function post_meta_info_shortcode($atts)
{
    global $post;
    if (!$post) {
        return '';
    }

    $iso_date = get_the_date('c', $post->ID);

    $formatted_date = get_the_date(get_option('date_format'), $post->ID);

    $first_name = get_the_author_meta('first_name', $post->post_author);
    $last_name = get_the_author_meta('last_name', $post->post_author);
    $author = trim($first_name . ' ' . $last_name);

    if (empty($author)) {
        $author = get_the_author_meta('display_name', $post->post_author);
    }

    $reading_time = get_reading_time($post->ID);

    ob_start();
?>
    <span class="article-meta">
        <?php if (!empty($author)): ?>
            <a
                rel="author"
                class="author"
                href="<?php echo home_url('/about-us/'); ?>"
                title="<?php echo esc_html($author); ?>"><?php echo esc_html($author); ?>
            </a>
        <?php endif; ?>
        <time datetime="<?php echo esc_attr($iso_date); ?>"><?php echo esc_html($formatted_date); ?></time>
        <span>
            <?php echo get_site_translation('reading_time'); ?>:
            <?php echo esc_html($reading_time); ?>
            <?php echo get_site_translation('mins'); ?>
        </span>
    </span>
<?php
    return ob_get_clean();
}

add_shortcode('post_meta', 'post_meta_info_shortcode');
