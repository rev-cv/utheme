<?php

/**
 * 1. Регистрация мета-полей для REST API
 */
add_action('init', function () {
    $fields = ['_custom_seo_title', '_custom_seo_desc'];
    foreach ($fields as $field) {
        register_post_meta('', $field, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }
});

/**
 * 2. Вставка JS-панели с правильными зависимостями
 */

/**
 * 2. Вставка JS-панели с принудительными зависимостями
 */
add_action('enqueue_block_editor_assets', function () {
    // Явно перечисляем все скрипты WP, которые должны быть загружены ДО нашего кода
    $dependencies = [
        'wp-plugins',
        'wp-edit-post',
        'wp-element',
        'wp-components',
        'wp-data',
        'wp-editor'
    ];

    // Регистрируем скрипт-пустышку с зависимостями
    wp_register_script('custom-seo-handler', '', $dependencies, '1.0', true);
    wp_enqueue_script('custom-seo-handler');

    $js_code = "
        (function() {
            var registerPlugin = wp.plugins.registerPlugin;
            var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
            var el = wp.element.createElement;
            var TextControl = wp.components.TextControl;
            var TextareaControl = wp.components.TextareaControl;
            var useSelect = wp.data.useSelect;
            var useDispatch = wp.data.useDispatch;

            var SEOPanel = function() {
                var data = useSelect(function(select) {
                    var editor = select('core/editor');
                    return {
                        meta: editor.getEditedPostAttribute('meta') || {},
                        postTitle: editor.getEditedPostAttribute('title') || '',
                        blocks: editor.getBlocks()
                    };
                });

                var editPost = useDispatch('core/editor').editPost;

                // Поиск текста для плейсхолдера
                var pBlock = data.blocks.find(function(b) { 
                    return b.name === 'core/paragraph' && b.attributes.content; 
                });
                var pText = pBlock ? pBlock.attributes.content.replace(/<[^>]*>?/gm, '') : '[No data]';

                var seoTitle = data.meta['_custom_seo_title'] || '';
                var seoDesc = data.meta['_custom_seo_desc'] || '';

                return el(PluginDocumentSettingPanel, {
                    name: 'custom-seo-panel',
                    title: 'SEO',
                    // icon: 'admin-site-alt3',
                }, [
                    el(TextareaControl, {
                        label: 'SEO Title',
                        value: seoTitle,
                        placeholder: data.postTitle,
                        onChange: function(val) {
                            var newMeta = Object.assign({}, data.meta, { _custom_seo_title: val });
                            editPost({ meta: newMeta });
                        },
                        help: 'Символов: ' + seoTitle.length + ' / 60'
                    }),
                    el(TextareaControl, {
                        label: 'Meta Description',
                        value: seoDesc,
                        placeholder: pText,
                        onChange: function(val) {
                            var newMeta = Object.assign({}, data.meta, { _custom_seo_desc: val });
                            editPost({ meta: newMeta });
                        },
                        help: 'Символов: ' + seoDesc.length + ' / 160'
                    })
                ]);
            };

            registerPlugin('custom-seo-panel-inline', {
                render: SEOPanel
            });
        })();
    ";

    wp_add_inline_script('custom-seo-handler', $js_code);
});

/*

Application API: 
Поля доступны по адресу wp-json/wp/v2/posts/<ID>. 
В объекте будет свойство meta, а в нем _custom_seo_title и _custom_seo_desc. 
Вы можете обновлять их обычным POST запросом.

Извлечение в коде: 
$seo_title = get_post_meta($post_id, '_custom_seo_title', true);
$seo_descr = get_post_meta($post_id, '_custom_seo_desc', true);

*/






/**
 * 1. ДОБАВЛЕНИЕ ПРЕФИКСА OG В ТЕГ HTML
 */
add_filter('language_attributes', function ($output) {
    return $output . ' prefix="og: https://ogp.me/ns#"';
});

/**
 * 2. ОСНОВНОЙ SEO ИНКЛУДЕР
 */
add_action('wp_head', 'my_custom_seo_head', 1);
function my_custom_seo_head()
{
    if (!is_singular()) return; // Работаем только с постами и страницами

    global $post;
    $post_id = $post->ID;

    // --- ПОДГОТОВКА ДАННЫХ ---

    // 1. TITLE
    $seo_title = get_post_meta($post_id, '_custom_seo_title', true);
    if (!$seo_title) {
        $seo_title = get_the_title($post_id);
    }
    $seo_title = esc_attr($seo_title);

    // 2. DESCRIPTION
    $seo_desc = get_post_meta($post_id, '_custom_seo_desc', true);

    if (!$seo_desc) {
        // Проверка по ID (ваши предустановленные описания для служебных страниц)
        $predefined_desc = [
            5 => get_bloginfo('name') . ": " . get_site_translation('kb_bets'),
            15 => get_site_translation('sitemap-description'),
        ];

        if (isset($predefined_desc[$post_id])) {
            $seo_desc = $predefined_desc[$post_id];
        } else {
            // Поиск первого параграфа
            $content = get_the_content();
            if (preg_match('/<p>(.*?)<\/p>/i', $content, $matches)) {
                $seo_desc = wp_strip_all_tags($matches[1]);
            }
        }
    }
    $seo_desc = wp_trim_words(esc_attr($seo_desc), 30, ''); // Ограничиваем длину

    // 3. IMAGE
    $img_id = get_post_thumbnail_id($post_id);
    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'full') : '';
    $img_alt = $img_id ? get_post_meta($img_id, '_wp_attachment_image_alt', true) : '';
    $img_type = $img_id ? get_post_mime_type($img_id) : '';

    $author_id = get_post_field('post_author', $post_id);
    $author_name = get_the_author_meta('display_name', $author_id);
    
    // --- ЛОГИКА ROBOTS ---
    // $is_utility = has_term('utility-pages', 'category', $post_id);
    // if ($is_utility) {
    //     $robots = 'noindex, follow';
    // } else {
    //     // Если не Utility, проверяем, не задано ли что-то вручную
    //     $robots = get_post_meta($post_id, '_custom_seo_robots', true) ?: 'index, follow';
    // }
    $robots = 'index, follow';

    // --- ВЫВОД МЕТА-ТЕГОВ ---

    echo "\n\n";
    echo '<title>' . $seo_title . "</title>\n";
    if ($seo_desc) {
        echo '<meta name="description" content="' . $seo_desc . "\" />\n";
    }

    // Open Graph
    echo '<meta property="og:locale" content="' . get_locale() . "\" />\n";
    echo '<meta property="og:type" content="article" ' . " />\n";
    echo '<meta property="og:title" content="' . $seo_title . "\" />\n";
    if ($seo_desc) {
        echo '<meta property="og:description" content="' . $seo_desc . "\" />\n";
    }
    echo '<meta property="og:url" content="' . get_permalink() . "\" />\n";
    echo '<meta property="og:site_name" content="' . get_bloginfo('name') . "\" />\n";
    echo '<meta property="og:updated_time" content="' . get_the_modified_date('c') . "\" />\n";

    if ($img_url) {
        echo '<meta property="og:image" content="' . $img_url . "\" />\n";
        echo '<meta property="og:image:secure_url" content="' . str_replace('http://', 'https://', $img_url) . "\" />\n";
        echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . "\" />\n";
        echo '<meta property="og:image:type" content="' . $img_type . "\" />\n";
    }

    // Article meta
    echo '<meta property="article:published_time" content="' . get_the_date('c') . "\" />\n";
    echo '<meta property="article:modified_time" content="' . get_the_modified_date('c') . "\" />\n";
    echo '<meta name="robots" content="' . esc_attr($robots) . '" />' . "\n";

    // Twitter
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . $seo_title . "\" />\n";
    if ($seo_desc) {
        echo '<meta name="twitter:description" content="' . $seo_desc . "\" />\n";
    }
    if ($img_url) {
        echo '<meta name="twitter:image" content="' . $img_url . "\" />\n";
    }

    // Стандартный тег (часто проверяется плагинами)
    echo '<meta name="author" content="' . $author_name . "\" />\n";
    echo '<meta name="publisher" content="' . $author_name . "\" />\n";

    // --- SCHEMA.ORG (JSON-LD) ---
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            // 1. WebPage
            [
                '@type' => 'WebPage',
                '@id' => get_permalink() . '#webpage',
                'url' => get_permalink(),
                'name' => $seo_title,
                'description' => $seo_desc,
                'isPartOf' => ['@id' => home_url('/#website')],
                'datePublished' => get_the_date('c'),
                'dateModified' => get_the_modified_date('c'),
            ],
            // 2. Article
            [
                '@type' => 'Article',
                '@id' => get_permalink() . '#article',
                'isPartOf' => ['@id' => get_permalink() . '#webpage'],
                'headline' => $seo_title,
                'datePublished' => get_the_date('c'),
                'dateModified' => get_the_modified_date('c'),
                'mainEntityOfPage' => ['@id' => get_permalink() . '#webpage'],
                'image' => $img_url ? ['@type' => 'ImageObject', 'url' => $img_url] : null,
                'publisher' => ['@id' => home_url('/#organization')], // Ссылка на блок ниже
                'author' => ['@id' => home_url('/#author')],      // Ссылка на блок ниже
            ],
            // 3. Organization (Издатель)
            [
                '@type' => 'Organization',
                '@id' => home_url('/#organization'),
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://vash-sait.com/logo.png', // УКАЖИТЕ ССЫЛКУ НА ЛОГОТИП
                ]
            ],
            // 4. Person (Автор)
            [
                '@type' => 'Person',
                '@id' => home_url('/#author'),
                'name' => $author_name,
                'url' => get_author_posts_url($author_id),
            ]
        ]
    ];
    // Удаляем пустые значения (например, если нет картинки)
    $schema['@graph'] = array_values(array_filter($schema['@graph']));

    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";

    echo "\n";
}

/**
 * УДАЛЯЕМ СТАНДАРТНЫЙ ВЫВОД TITLE (чтобы не дублировался)
 */
remove_action('wp_head', '_wp_render_title_tag', 1);
