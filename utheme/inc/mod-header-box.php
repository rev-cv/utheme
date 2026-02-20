<?php

/**
 * Извлекает блок .header-box из HTML контента.
 * Возвращает массив ['header' => html_блока, 'content' => остальной_html]
 */
function extract_header_box_from_html($content)
{
    $header_box = '';

    if (!empty($content) && class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Оборачиваем в div и задаем кодировку, чтобы DOMDocument корректно обработал фрагмент и UTF-8
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " header-box ")]');

        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            $header_box = $dom->saveHTML($node);
            $header_box = str_replace(['&gt;', ']]>'], ['>', ']]&gt;'], $header_box);
            $node->parentNode->removeChild($node);

            // Пересобираем контент, беря только содержимое div-обертки
            $content = '';
            $container = $dom->getElementsByTagName('div')->item(0);
            if ($container) {
                foreach ($container->childNodes as $child) {
                    $content .= $dom->saveHTML($child);
                }
                $content = str_replace(['&gt;', ']]>'], ['>', ']]&gt;'], $content);
            }
        }
        libxml_clear_errors();
    }

    return [
        'header' => $header_box,
        'content' => $content
    ];
}
