<?php

/*
Подключает стили и скрипты темы.
Функция использует массивы для определения списка CSS и JS файлов.
*/
function mytheme_scripts()
{
    $theme_version = '1.1';
    $template_uri = get_template_directory_uri();

    $styles = [
        'mytheme-style' => '/src/style.css',
    ];

    // foreach ($styles as $handle => $path) {
    //     wp_enqueue_style($handle, $template_uri . $path, [], $theme_version);
    // }

    foreach ($styles as $handle => $path) {
        $file_path = get_template_directory() . $path; // Физический путь к файлу
        
        if (file_exists($file_path)) {
            $css_content = file_get_contents($file_path);
            // Выводим CSS прямо в head
            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $css_content);
        } else {
            // Если файла нет, подключаем как обычно (на всякий случай)
            wp_enqueue_style($handle, $template_uri . $path, [], $theme_version);
        }
    }

    // 2. Подключаем скрипты
    // Каждый скрипт — это отдельный элемент с уникальным handle
    $scripts = [
        'mytheme-scroll-to-top' => [
            'path' => '/src/scroll-to-top/scroll-to-top.js',
            'deps' => [], // Пустой массив, если зависимостей нет
        ],
        // 'mytheme-multi-level' => [
        //     'path' => '/src/main-menu_multi-level/multi-level.js',
        //     'deps' => [],
        // ],
        // 'mytheme-aside-menu' => [
        //     'path' => '/src/main-menu_aside/menu.js',
        //     'deps' => [],
        // ],
        // 'fast-nav' => [
        //     'path' => '/src/art__fast-nav/fast-nav.js',
        //     'deps' => [],
        // ],
    ];

    foreach ($scripts as $handle => $details) {
        wp_enqueue_script(
            $handle,
            $template_uri . $details['path'],
            $details['deps'],
            $theme_version,
            true // Загрузка в футере
        );
    }
}
add_action('wp_enqueue_scripts', 'mytheme_scripts');


function remove_default_theme_styles() {
    wp_dequeue_style('my-theme-style'); 
}
add_action('wp_enqueue_scripts', 'remove_default_theme_styles', 20);