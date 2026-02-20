<?php
/*
Template Name: ARTICLE 2 COLUMNS

КАК ИСПОЛЬЗОВАТЬ:
Этот шаблон автоматически создает двухколоночную верстку.
1. Основной контент страницы отображается в левой, широкой колонке.
2. Любой блок (группа, абзац, шорткод и т.д.) в редакторе Gutenberg, которому вы добавите
   CSS-класс "aside-element", будет автоматически вырезан из основного контента и помещен
   в правую, узкую колонку (сайдбар).
3. Блок с классом "header-box" будет вырезан из контента и помещен НАД основным контейнером <main>,
   на всю ширину экрана (например, для Hero-секции).
*/
?>
<?php get_header() ?>

<?php while (have_posts()) : the_post(); ?>
    <?php
    // 1. Временно отключаем инъекцию, чтобы она не мешала нашему парсингу DOM
    remove_filter('the_content', 'my_theme_dynamic_content_injection');

    $raw_content = get_the_content();
    $raw_content = apply_filters('the_content', $raw_content);
    $raw_content = str_replace(']]>', ']]&gt;', $raw_content);

    // 2. Извлекаем header-box, если он есть
    $extracted = extract_header_box_from_html($raw_content);
    $main_content = $extracted['content'];
    $aside_content = '';

    // 3. Извлекаем элементы для сайдбара (.aside-element) из "чистого" основного контента
    if (strpos($main_content, 'aside-element') !== false) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Оборачиваем в div и добавляем XML-декларацию кодировки, чтобы DOMDocument корректно обработал UTF-8 без повреждения CSS
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $main_content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        // Выбираем элементы с классом aside-element, которые НЕ находятся внутри других элементов с таким же классом
        $class_check = "contains(concat(' ', normalize-space(@class), ' '), ' aside-element ')";
        $nodes = $xpath->query("//*[{$class_check} and not(ancestor::*[{$class_check}])]");

        if ($nodes->length > 0) {
            foreach ($nodes as $node) {
                $aside_content .= $dom->saveHTML($node);
                $node->parentNode->removeChild($node);
            }
            $aside_content = str_replace(['&gt;', ']]>'], ['>', ']]&gt;'], $aside_content);

            // Собираем обратно основной контент (внутренности div-обертки)
            $main_content = '';
            $container = $dom->getElementsByTagName('div')->item(0);
            if ($container && $container->hasChildNodes()) {
                foreach ($container->childNodes as $child) {
                    $main_content .= $dom->saveHTML($child);
                }
                $main_content = str_replace(['&gt;', ']]>'], ['>', ']]&gt;'], $main_content);
            }
        }
        libxml_clear_errors();
    }

    // 4. Теперь, когда основной контент "чист" (без сайдбара), вручную применяем инъекцию TOC и шорткода
    if (function_exists('my_theme_dynamic_content_injection')) {
        $main_content = my_theme_dynamic_content_injection($main_content);
    }

    ?>
    <?php echo $extracted['header']; ?>
    <main<?php echo !empty($aside_content) ? ' class="main-2-columns"' : ''; ?>>
        <article class="article">
            <?php if (!is_front_page() && function_exists("rank_math_the_breadcrumbs")) rank_math_the_breadcrumbs(); ?>
            <?php echo $main_content; // Выводим основной контент, уже с инъекцией 
            ?>
        </article>
        <?php if (!empty($aside_content)) : ?>
            <aside>
                <?php echo $aside_content; // Выводим отдельно контент сайдбара 
                ?>
            </aside>
        <?php endif; ?>
        </main>
    <?php endwhile; ?>
    <?php
    // 5. Возвращаем фильтр на место для других циклов/страниц
    add_filter('the_content', 'my_theme_dynamic_content_injection');
    get_footer();
