<?php

/**
 * Получает конфигурацию темы из SCSS файла.
 * Использует static переменную для кэширования результата парсинга,
 * чтобы файл читался только один раз за генерацию страницы.
 *
 * @param string|null $key Ключ настройки (например, 'main-menu'). Если null, возвращает весь массив.
 * @param mixed $default Значение по умолчанию, если ключ не найден.
 * @return mixed
 */
function my_theme_get_config($key = null, $default = null)
{
    static $config = null;

    // Читаем файл только если $config еще не заполнен (первый вызов)
    if ($config === null) {
        $config = [];
        $conf_file = get_template_directory() . '/src/conf.scss';

        if (file_exists($conf_file)) {
            $content = file_get_contents($conf_file);

            // Парсим нужные переменные
            if (preg_match('/\$main-menu:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $m)) {
                $config['main-menu'] = $m[1];
            }
            if (preg_match('/\$footer-menu:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $m)) {
                $config['footer-menu'] = $m[1];
            }
            if (preg_match('/\$font-vibe:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $m)) {
                $config['font-vibe'] = $m[1];
            }
            if (preg_match('/\$is-not-section:\s*["\']?(true|false)["\']?/', $content, $m)) {
                $config['is-not-section'] = ($m[1] === 'true');
            }
            if (preg_match('/\$article-card:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $m)) {
                $config['article-card'] = $m[1];
            }
        }
    }

    if ($key === null) {
        return $config;
    }

    return isset($config[$key]) ? $config[$key] : $default;
}