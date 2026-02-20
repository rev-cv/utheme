<?php

// автоматически задает всем картинкам внутри контента ленивую загрузку
// за исключением самой первой
function optimize_images_loading_priority($content)
{
    if (empty($content)) {
        return $content;
    }

    $pattern = '/<img([^>]+)>/i';
    $counter = 0;

    $content = preg_replace_callback($pattern, function ($matches) use (&$counter) {
        $img_tag = $matches[0];
        $counter++;

        if ($counter === 1) {
            // Оптимизация для ПЕРВОЙ картинки (LCP)
            
            // 1. Убираем lazy-loading, если он есть
            $img_tag = preg_replace('/loading=["\']?lazy["\']?/i', '', $img_tag);
            
            // 2. Добавляем высокий приоритет загрузки
            if (strpos($img_tag, 'fetchpriority') === false) {
                $img_tag = str_replace('<img', '<img fetchpriority="high"', $img_tag);
            }
            
            // 3. Убираем атрибут decodings="async" для первой картинки (лучше для LCP)
            $img_tag = preg_replace('/decoding=["\']?async["\']?/i', '', $img_tag);
            
        } else {
            // Оптимизация для ОСТАЛЬНЫХ картинок
            
            // Добавляем lazy loading, если его нет
            if (strpos($img_tag, 'loading="lazy"') === false) {
                $img_tag = str_replace('<img', '<img loading="lazy"', $img_tag);
            }
            
            // Добавляем асинхронное декодирование для плавности скролла
            if (strpos($img_tag, 'decoding="async"') === false) {
                $img_tag = str_replace('<img', '<img decoding="async"', $img_tag);
            }
        }

        // Чистим лишние пробелы, которые могли возникнуть при заменах
        $img_tag = preg_replace('/\s\s+/', ' ', $img_tag);

        return $img_tag;
    }, $content);

    return $content;
}

add_filter('the_content', 'optimize_images_loading_priority', 10);




