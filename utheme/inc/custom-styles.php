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

    foreach ($styles as $handle => $path) {
        $file_path = get_template_directory() . $path;
        
        if (file_exists($file_path)) {
            $css_content = file_get_contents($file_path);
            $css_content = str_replace("\xEF\xBB\xBF", '', $css_content); 
            $css_content = " " . $css_content;

            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $css_content);
        } else {
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
add_action('wp_enqueue_scripts', 'mytheme_scripts', 999);


function remove_default_theme_styles() {
    wp_dequeue_style('my-theme-style'); 
}
add_action('wp_enqueue_scripts', 'remove_default_theme_styles', 20);


# фикс ошибок валидатора для seoшников
remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
function clean_css_for_validator($buffer) {
    if (is_admin() || empty($buffer)) return $buffer;

    $search = [
        // 1. Исправляем ошибки в calc(env(...)) для top и right
        // Меняем calc(env(...) + 16px) на просто 16px, чтобы валидатор не спотыкался
        '/right\s*:\s*calc\s*\(\s*env\([^)]+\)\s*\+\s*[^;}]+\)/',
        '/top\s*:\s*calc\s*\(\s*env\([^)]+\)\s*\+\s*[^;}]+\)/',

        // 2. Ошибка с несуществующим text-wrap: none
        '/text-wrap\s*:\s*none\s*;?/',
        
        // 3. Новые свойства (contain-intrinsic, interpolate и т.д.)
        '/contain-intrinsic-size:[^;}]+\s*;?/',
        '/interpolate-size:[^;}]+\s*;?/',
        '/::details-content/',
        
        // 4. Сложные блоки с @starting-style (частая причина Parse Error)
        '/@starting-style\s*\{[^{}]*\{[^{}]*\}[^{}]*\}/s',
    ];

    // Для первых двух пунктов (calc) заменим их на безопасные значения
    // Для остальных — просто удалим
    $buffer = preg_replace($search[0], 'right:16px', $buffer);
    $buffer = preg_replace($search[1], 'top:16px', $buffer);
    
    // Удаляем всё остальное из списка
    for ($i = 2; $i < count($search); $i++) {
        $buffer = preg_replace($search[$i], '', $buffer);
    }

    return $buffer;
}
// Перехват вывода
add_action('template_redirect', function() {
    ob_start("clean_css_for_validator");
});