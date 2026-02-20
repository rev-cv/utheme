<?php


function get_reading_time($post_id = null)
{
    // Получает данные записи по ID (текущая запись по умолчанию)
    $post = get_post($post_id);

    // Если пост не найден или у него нет контента, возвращаем 0
    if (!$post || empty($post->post_content)) {
        return 0;
    }

    // Удаляет HTML-теги из контента для чистого текста
    $content = strip_tags($post->post_content);

    // Подсчитывает количество слов в тексте
    $word_count = str_word_count($content);

    // Рассчитывает время чтения: слова делятся на 180 (средняя скорость чтения в минуту)
    // и округляется в большую сторону
    $minutes = ceil($word_count / 180);

    // Возвращает количество минут для прочтения
    return $minutes;
}

/**
 * Шорткод для вывода мета-информации о статье: дата публикации и время чтения.
 *
 * Использование: [post_meta]
 *
 * @param array $atts Атрибуты шорткода (не используются).
 * @return string HTML-разметка с датой и временем чтения.
 */
function post_meta_info_shortcode($atts)
{
    // Получаем глобальный объект поста
    global $post;
    if (!$post) {
        return '';
    }

    // Получаем дату публикации в формате ISO 8601 для атрибута datetime
    $iso_date = get_the_date('c', $post->ID);

    // Получаем дату публикации в нужном формате (напр., "15 de Janeiro de 2025")
    // Формат зависит от локали сайта (Настройки -> Общие -> Язык сайта)
    $formatted_date = get_the_date(get_option('date_format'), $post->ID);

    $first_name = get_the_author_meta('first_name', $post->post_author);
    $last_name = get_the_author_meta('last_name', $post->post_author);
    $author = trim($first_name . ' ' . $last_name);

    if (empty($author)) {
        $author = get_the_author_meta('display_name', $post->post_author);
    }

    // Получаем время чтения из нашей функции
    $reading_time = get_reading_time($post->ID);

    // Начинаем собирать HTML
    ob_start();
?>
    <span class="article-meta">
        <a
            rel="author"
            class="author"
            href="<?php echo get_author_posts_url($post->post_author); ?>"
            title="<?php echo esc_html($author); ?>"><?php echo esc_html($author); ?>
        </a>
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
