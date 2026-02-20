<?php

/*
Подключает стили и скрипты темы.
Функция использует массивы для определения списка CSS и JS файлов.
*/
function mytheme_scripts()
{
    $theme_version = '1.0';
    $template_uri = get_template_directory_uri();

    $styles = [
        'mytheme-style' => '/src/style.css',
    ];

    foreach ($styles as $handle => $path) {
        wp_enqueue_style($handle, $template_uri . $path, [], $theme_version);
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
