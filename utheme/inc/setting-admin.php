<?php

function my_theme_setup()
{

    // Включает автоматическое управление тегом <title>
    add_theme_support('title-tag');

    // Добавляет поддержку кастомного логотипа
    add_theme_support('custom-logo');

    // Включает поддержку миниатюр (featured images) для записей и страниц
    add_theme_support('post-thumbnails');

    // Отключает отображение админской панели на сайте
    show_admin_bar(false);

    // Регистрирует две области меню: основное и футерное
    register_nav_menus(
        array(
            'header-menu' => __('MainMenu'),
            'footer-menu' => __('FooterMenu')
        )
    );

    // Включает поддержку HTML5 разметки для различных элементов
    add_theme_support(
        'html5',
        array(
            'search-form',   // Форма поиска
            'comment-form',  // Форма комментариев
            'comment-list',  // Список комментариев
            'gallery',       // Галерея
            'caption',       // Подписи к изображениям
            'style',         // HTML-стили
            'script',        // HTML-скрипты
        )
    );

    // Включает выборочное обновление виджетов в кастомайзере без перезагрузки страницы
    // add_theme_support('customize-selective-refresh-widgets');

    add_filter('wp_is_application_passwords_available', '__return_true');
}

add_action('after_setup_theme', 'my_theme_setup');




function custom_dequeue_gutenberg_styles()
{
    // 1. Определение шаблонов для исключения
    $excluded_templates = array(
        'article.php',
        'articles.php',
        'home.php',
    );

    // 2. Проверка, используется ли один из исключенных шаблонов
    if (is_page_template($excluded_templates)) {
        // Отключаем стили, так как мы находимся на "безопасном" шаблоне
        wp_dequeue_style('wp-block-library');        // Основные стили Gutenberg
        wp_dequeue_style('wp-block-library-theme');  // Тематические стили Gutenberg
        wp_dequeue_style('global-styles');           // Глобальные стили (от FSE)
    }
}

// Прикрепляем функцию к правильному хуку
add_action('wp_enqueue_scripts', 'custom_dequeue_gutenberg_styles', 999);




// Функция для ограничения доступа к REST API WordPress
function disable_rest_api()
{
    // Добавляет фильтр для проверки аутентификации в REST API
    add_filter('rest_authentication_errors', function ($result) {
        if (!empty($result)) {
            return $result;
        }
        // Разрешает доступ к REST API только авторизованным пользователям
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', 'REST API', array('status' => 401));
        }
        return $result;
    });

    // Удаляет ссылку на REST API из секции <head> HTML-документа
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    // Удаляет REST API ссылку из HTTP-заголовков
    remove_action('template_redirect', 'rest_output_link_header', 11);
}

// Запускает функцию ограничения REST API на событие 'init'
add_action('init', 'disable_rest_api');




// Функция для полного отключения системы комментариев в WordPress
function disable_comments()
{
    // Закрывает комментарии и пинги (уведомления) для всех записей
    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);

    // Очищает массив комментариев - возвращает пустой массив вместо существующих комментариев
    add_filter('comments_array', '__return_empty_array', 10, 2);

    // Удаляет пункт "Комментарии" из меню админ-панели
    add_action('admin_menu', function () {
        remove_menu_page('edit-comments.php');
    });

    // Удаляет пункт "Комментарии" из админ-панели (верхняя панель инструментов)
    add_action('wp_before_admin_bar_render', function () {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    });

    // Отключает поддержку комментариев и трекбэков для всех типов записей
    add_action('init', function () {
        foreach (get_post_types() as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }
    });
}
// Запускает функцию отключения комментариев
add_action('init', 'disable_comments');




// Функция для удаления стандартных виджетов WordPress
function disable_default_widgets()
{
    // Удаляет все встроенные виджеты WordPress из панели виджетов
    unregister_widget('WP_Widget_Pages');           // Виджет страниц
    unregister_widget('WP_Widget_Calendar');        // Календарь
    unregister_widget('WP_Widget_Archives');        // Архивы
    unregister_widget('WP_Widget_Links');           // Ссылки
    unregister_widget('WP_Widget_Meta');            // Мета-виджет
    unregister_widget('WP_Widget_Search');          // Поиск
    unregister_widget('WP_Widget_Text');            // Текстовый виджет
    unregister_widget('WP_Widget_Categories');      // Рубрики
    unregister_widget('WP_Widget_Recent_Posts');    // Свежие записи
    unregister_widget('WP_Widget_Recent_Comments'); // Свежие комментарии
    unregister_widget('WP_Widget_RSS');             // RSS
    unregister_widget('WP_Widget_Tag_Cloud');       // Облако меток
    unregister_widget('WP_Nav_Menu_Widget');        // Произвольное меню
}
// Запускает функцию удаления виджетов с низким приоритетом (11)
add_action('widgets_init', 'disable_default_widgets', 11);




// Функция для повышения безопасности процесса входа в WordPress
function login_security_measures()
{
    // Изменяет сообщения об ошибках при входе для защиты от перебора
    add_filter('login_errors', function () {
        return 'Incorrect data!'; // Универсальное сообщение об ошибке
    });
}
// Запускает меры безопасности при инициализации WordPress
add_action('init', 'login_security_measures');




// Функция для блокировки выполнения PHP-скриптов в папке uploads
function disable_php_in_uploads()
{
    // Определяет путь к файлу .htaccess в папке загрузок
    $htaccess = ABSPATH . '/wp-content/uploads/.htaccess';

    // Проверяет, существует ли уже файл .htaccess
    if (!file_exists($htaccess)) {
        // Создает .htaccess с директивой отключения PHP
        file_put_contents($htaccess, 'php_flag engine off');
    }
}
// Запускает функцию блокировки PHP при инициализации WordPress
add_action('init', 'disable_php_in_uploads');




// Функция для полного отключения XML-RPC в WordPress
function disable_xmlrpc()
{
    // Отключает XML-RPC API через фильтр
    add_filter('xmlrpc_enabled', '__return_false');
    // Отключает пинги (связанные с XML-RPC)
    add_filter('pings_open', '__return_false');
    // Удаляет RSD (Really Simple Discovery) ссылку из заголовка
    remove_action('wp_head', 'rsd_link');
    // Удаляет генератор WordPress (версию) из заголовка
    remove_action('wp_head', 'wp_generator');

    // Блокирует прямой доступ к xmlrpc.php через HTTP-заголовки
    if (strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false) {
        header('HTTP/1.0 403 Forbidden'); // Возвращает статус "Запрещено"
        exit; // Немедленно завершает выполнение скрипта
    }
}
// Запускает функцию отключения XML-RPC при инициализации
add_action('init', 'disable_xmlrpc');




// Функция для очистки опасных HTTP-заголовков
function clean_dangerous_headers()
{
    // Массив заголовков, которые могут раскрывать информацию о системе
    $headers = array(
        'X-Powered-By',     // Раскрывает технологию (PHP, ASP.NET и т.д.)
        'Server',           // Раскрывает информацию о веб-сервере
        'X-AspNet-Version'  // Раскрывает версию ASP.NET
    );

    // Удаляет каждый опасный заголовок из ответа
    foreach ($headers as $header) {
        header_remove($header);
    }
}
// Запускает очистку заголовков при отправке HTTP-заголовков
add_action('send_headers', 'clean_dangerous_headers');





// Функция для отключения пингбэков (уведомлений о ссылках) в WordPress
function disable_pingback()
{
    // Фильтрует методы XML-RPC и удаляет метод pingback.ping
    add_filter('xmlrpc_methods', function ($methods) {
        unset($methods['pingback.ping']); // Удаляет метод пингбэка
        return $methods;
    });
}
// Запускает функцию отключения пингбэков при вызове XML-RPC
add_action('xmlrpc_call', 'disable_pingback');





// Сортировка страниц по дате в админ-панели
function custom_pages_admin_order($query)
{
    // Проверяем, что мы в админке, это основной запрос и тип контента — page
    if (is_admin() && $query->is_main_query() && $query->get('post_type') == 'page') {
        $query->set('orderby', 'date');
        $query->set('order', 'DESC'); // DESC — от новых к старым, ASC — наоборот
    }
}
add_action('pre_get_posts', 'custom_pages_admin_order');




// 1. Управляем списком колонок (удаляем лишние и добавляем новую)
add_filter('manage_pages_columns', 'custom_pages_columns_filter');
function custom_pages_columns_filter($columns)
{
    // Удаляем ненужные колонки
    unset($columns['comments']);
    // unset($columns['author']);

    // Добавляем колонку Ярлык (slug)
    $columns['slug'] = 'Slug';

    return $columns;
}

// Выводим содержимое для новой колонки slug
add_action('manage_pages_custom_column', 'custom_pages_columns_content', 10, 2);
function custom_pages_columns_content($column_name, $post_id)
{
    if ($column_name == 'slug') {
        $post = get_post($post_id);
        echo '<code style="background: #f0f0f1; padding: 3px 5px; border-radius: 3px;">/' . $post->post_name . '/</code>';
    }
}



function add_categories_to_pages()
{
    register_taxonomy_for_object_type('category', 'page');
}
add_action('init', 'add_categories_to_pages');





/**
 * Отключение стандартных скриптов и стилей WP на определенных страницах
 */
function my_theme_disable_wp_assets()
{

    // УСЛОВИЕ: Здесь укажите, где именно отключать.
    // Например: is_page_template('page-custom.php') или is_front_page()
    // В данном примере отключаем везде, кроме админки. 
    // Измените условие под себя!
    if (! is_admin()) {

        // 1. Отключаем стили редактора блоков (Gutenberg)
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style'); // Если есть WooCommerce

        // 2. Отключаем "Global Styles" (огромный блок inline-CSS с переменными)
        wp_dequeue_style('global-styles');

        // 3. Отключаем стили классической темы (появились в WP 6.1)
        wp_dequeue_style('classic-theme-styles');

        // 4. Если нужно убрать лишние SVG фильтры, которые WP добавляет в body
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
    }
}
add_action('wp_enqueue_scripts', 'my_theme_disable_wp_assets', 100);


/**
 * Отключение Emoji (смайликов WP), которые грузят лишний JS
 */
function my_theme_disable_emojis()
{
    // То же самое условие
    if (! is_admin()) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        // Отключаем DNS-prefetch для s.w.org
        add_filter('emoji_svg_url', '__return_false');
    }
}
add_action('init', 'my_theme_disable_emojis');







add_filter('intermediate_image_sizes_advanced', function($sizes) {
    // Оставляем только 'thumbnail', остальное удаляем из очереди генерации
    unset($sizes['medium']);
    unset($sizes['large']);
    unset($sizes['medium_large']); // 768px
    unset($sizes['1536x1536']);    // 2x medium_large
    unset($sizes['2048x2048']);    // 2x large
    return $sizes;
});

// Отключаем создание масштабированных больших изображений (scaled)
add_filter('big_image_size_threshold', '__return_false');