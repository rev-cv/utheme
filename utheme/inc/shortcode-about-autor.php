<?php

function wp_author_box_shortcode($atts)
{
    // Получаем ID автора текущего поста
    $author_id = get_the_author_meta('ID');

    // получить био из Rank Math
    $rm_bio = get_user_meta($author_id, 'rank_math_description', true);

    // если в Rank Math пусто - стандартное описание WP
    $default_bio = !empty($rm_bio) ? $rm_bio : get_the_author_meta('description', $author_id);

    // Настройки по умолчанию из профиля WP
    $default_args = array(
        'name'  => get_the_author_meta('display_name', $author_id),
        // 'image' => get_avatar_url($author_id, ['size' => 200]),
        'image' => "/wp-content/uploads/Goncalo-Tavares.webp",
        'bio'   => $default_bio,
    );

    // Смешиваем дефолты с тем, что ввел пользователь в шорткоде
    $args = shortcode_atts($default_args, $atts);

    ob_start();
?>
    <div class="author-sidebar-box">
        <?php if ($args['image']): ?>
            <div class="author-image">
                <img src="<?php echo esc_url($args['image']); ?>" alt="<?php echo esc_attr($args['name']); ?>">
            </div>
        <?php endif; ?>

        <h3 class="author-name"><?php echo esc_html($args['name']); ?></h3>

        <?php if ($args['bio']): ?>
            <p class="author-bio"><?php echo esc_html($args['bio']); ?></p>
        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('about_author', 'wp_author_box_shortcode');
