<?php

/**
 * 1. Регистрация мета-полей для REST API
 */
add_action('init', function () {
    $fields = ['_custom_seo_title', '_custom_seo_desc', '_custom_seo_headline'];
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
    register_post_meta('', '_schema_html', [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => function ($v) { return $v; },
        'auth_callback'     => function () {
            return current_user_can('edit_posts');
        }
    ]);
});

/**
 * 2. Вставка JS-панели (Админка)
 */
add_action('enqueue_block_editor_assets', function () {
    $dependencies = ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-editor'];
    wp_register_script('custom-seo-handler', '', $dependencies, '1.1', true);
    wp_enqueue_script('custom-seo-handler');

    $js_code = "
        (function() {
            var el = wp.element.createElement;
            var TextareaControl = wp.components.TextareaControl;
            var Button = wp.components.Button;
            var Modal = wp.components.Modal;
            var useState = wp.element.useState;
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

                var _s1 = useState(false);
                var schemaModalOpen = _s1[0];
                var setSchemaModalOpen = _s1[1];

                var _s2 = useState('');
                var schemaEditing = _s2[0];
                var setSchemaEditing = _s2[1];

                var pBlock = data.blocks.find(function(b) {
                    return b.name === 'core/paragraph' && b.attributes.content;
                });
                var pText = pBlock ? pBlock.attributes.content.replace(/<[^>]*>?/gm, '') : '';

                var seoTitle    = data.meta['_custom_seo_title']    || '';
                var seoDesc     = data.meta['_custom_seo_desc']     || '';
                var seoHeadline = data.meta['_custom_seo_headline'] || '';
                var schemaHtml  = data.meta['_schema_html']         || '';

                var children = [
                    el(TextareaControl, {
                        label: 'SEO Title',
                        value: seoTitle,
                        placeholder: data.postTitle,
                        onChange: function(val) { editPost({ meta: Object.assign({}, data.meta, { _custom_seo_title: val }) }); },
                        help: 'Символов: ' + seoTitle.length + ' / 60'
                    }),
                    el(TextareaControl, {
                        label: 'Social / Headline',
                        value: seoHeadline,
                        placeholder: seoTitle || data.postTitle,
                        onChange: function(val) { editPost({ meta: Object.assign({}, data.meta, { _custom_seo_headline: val }) }); }
                    }),
                    el(TextareaControl, {
                        label: 'Meta Description',
                        value: seoDesc,
                        placeholder: pText.substring(0, 160),
                        onChange: function(val) { editPost({ meta: Object.assign({}, data.meta, { _custom_seo_desc: val }) }); },
                        help: 'Символов: ' + seoDesc.length + ' / 160'
                    }),
                    el(Button, {
                        variant: 'secondary',
                        style: { marginTop: '4px', width: '100%', justifyContent: 'center' },
                        onClick: function() {
                            setSchemaEditing(schemaHtml);
                            setSchemaModalOpen(true);
                        }
                    }, 'JSON-LD Schema ↗')
                ];

                if (schemaModalOpen) {
                    children.push(
                        el(Modal, {
                            title: 'JSON-LD Schema',
                            onRequestClose: function() { setSchemaModalOpen(false); },
                            style: { width: '66vw', maxWidth: '1200px', minHeight: '66vh' }
                        }, [
                            el('textarea', {
                                value: schemaEditing,
                                onChange: function(e) { setSchemaEditing(e.target.value); },
                                style: {
                                    width: '100%',
                                    height: 'calc(66vh - 140px)',
                                    minHeight: '300px',
                                    fontFamily: 'monospace',
                                    fontSize: '12px',
                                    lineHeight: '1.5',
                                    boxSizing: 'border-box',
                                    border: '1px solid #ddd',
                                    borderRadius: '2px',
                                    padding: '8px',
                                    resize: 'none'
                                }
                            }),
                            el('div', {
                                style: { display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '12px' }
                            }, [
                                el(Button, {
                                    variant: 'tertiary',
                                    onClick: function() { setSchemaModalOpen(false); }
                                }, 'Отмена'),
                                el(Button, {
                                    variant: 'primary',
                                    onClick: function() {
                                        editPost({ meta: Object.assign({}, data.meta, { _schema_html: schemaEditing }) });
                                        setSchemaModalOpen(false);
                                    }
                                }, 'Сохранить')
                            ])
                        ])
                    );
                }

                return el(wp.editPost.PluginDocumentSettingPanel, {
                    name: 'custom-seo-panel',
                    title: 'SEO Settings',
                }, children);
            };

            wp.plugins.registerPlugin('custom-seo-panel-inline', { render: SEOPanel });
        })();
    ";
    wp_add_inline_script('custom-seo-handler', $js_code);
});

/**
 * 3. Исправление Canonical для пагинации
 */
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', function() {
    if (!is_singular()) return;
    $link = get_permalink();
    $page = get_query_var('page');
    $paged = get_query_var('paged');
    
    if ($page > 1 || $paged > 1) {
        $n = max($page, $paged);
        $link = trailingslashit($link) . "page/" . $n . "/"; 
        // Примечание: для постов внутри страниц используется user_trailingslashit или замена в зависимости от ЧПУ
    }
    echo '<link rel="canonical" href="' . esc_url($link) . '" />' . "\n";
});

/**
 * 4. Основной вывод SEO и Schema.org
 */

// true = @id страницы и author статьи собраны буквально как в примере ТЗ (голый URL, встроенный
// дубль Organization). false = более строгий вариант с однозначными @id-ссылками (URL#webpage,
// ссылка на Organization). Оба места (webpage_id/article_author) читают этот флаг, так что
// переключение не рассинхронивает граф.
define('UT_SCHEMA_LITERAL_SPEC', true);

/**
 * Вытаскивает вопросы/ответы настоящего FAQ (помеченные маркером <!-- ut:faq --> в пайплайне,
 * core/wp_html.py) из сырого post_content — до применения фильтров the_content. Возвращает
 * массив сущностей Question для FAQPage.mainEntity, либо [] если на странице нет FAQ-блоков.
 */
function ut_extract_faq_entities($post_id) {
    $content = get_post_field('post_content', $post_id);
    if (!$content || strpos($content, '<!-- ut:faq -->') === false) {
        return [];
    }

    $pattern = '/<!--\s*ut:faq\s*-->\s*<!--\s*wp:details\s*-->\s*<details[^>]*><summary[^>]*>(.*?)<\/summary>(.*?)<\/details>\s*<!--\s*\/wp:details\s*-->/s';
    if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $entities = [];
    foreach ($matches as $m) {
        $question = trim(wp_strip_all_tags($m[1]));
        $answer   = trim(wp_strip_all_tags($m[2]));
        if ($question === '' || $answer === '') continue;

        $entities[] = [
            "@type" => "Question",
            "name"  => $question,
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text"  => $answer,
            ],
        ];
    }

    return $entities;
}

add_action('wp_head', 'my_custom_seo_head', 1);
function my_custom_seo_head() {
    if (!is_singular()) return;

    global $post;
    $post_id    = $post->ID;
    $site_name  = get_bloginfo('name');
    $site_lang  = get_bloginfo('language');
    $curr_date  = get_the_modified_date('c') ?: current_time('c');

    // Данные из мета-полей
    $seo_title    = replace_placeholders_safely(get_post_meta($post_id, '_custom_seo_title', true)) ?: get_the_title($post_id);
    $seo_desc_raw = replace_placeholders_safely(get_post_meta($post_id, '_custom_seo_desc', true)) ?: get_the_excerpt($post_id);
    $seo_desc     = wp_trim_words(esc_attr($seo_desc_raw), 30, '');
    $social_headline = replace_placeholders_safely(get_post_meta($post_id, '_custom_seo_headline', true)) ?: $seo_title;

    $img_url  = get_the_post_thumbnail_url($post_id, 'full') ?: '';
    $logo_id  = get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

    // Номер страницы пагинации — суффикс в title начиная со 2-й страницы
    $page_num  = max((int) get_query_var('page'), (int) get_query_var('paged'));
    $title_out = $seo_title;
    if ($page_num > 1) {
        $title_out .= ' – ' . get_site_translation('page') . ' ' . $page_num;
    }

    // Вывод тегов (всегда — независимо от того, какая часть графа ниже будет выведена/подавлена)
    echo "\n<title>" . esc_attr($title_out) . "</title>\n";
    if ($seo_desc) echo '<meta name="description" content="' . $seo_desc . "\" />\n";

    // Open Graph & Twitter
    echo '<meta property="og:type" content="article" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($social_headline) . "\" />\n";
    echo '<meta property="og:url" content="' . get_permalink() . "\" />\n";
    if ($img_url) {
        echo '<meta property="og:image" content="' . esc_url($img_url) . "\" />\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }

    $is_list    = is_page_template('page-list.php');
    $is_utility = has_category('Utility Pages', $post_id);

    // Страницы политик/утилитарные (кроме листингов — у них своя ветка ниже) — без микроразметки вообще.
    if ($is_utility && !$is_list) {
        return;
    }

    $webpage_id = UT_SCHEMA_LITERAL_SPEC ? get_permalink() : get_permalink() . '#webpage';

    // 1. Organization (общая часть графа, повторяется на каждой странице с одним и тем же @id)
    $org = [
        "@type" => "Organization",
        "@id"   => home_url('/#organization'),
        "name"  => $site_name,
        "url"   => home_url('/'),
    ];
    if ($logo_url) {
        $org["logo"] = [
            "@type"      => "ImageObject",
            "@id"        => home_url('/#logo'),
            "url"        => $logo_url,
            "contentUrl" => $logo_url,
            "caption"    => $site_name,
            "inLanguage" => $site_lang,
        ];
    }

    // 2. WebSite
    $website = [
        "@type" => "WebSite",
        "@id"   => home_url('/#website'),
        "url"   => home_url('/'),
        "name"  => $site_name,
        "publisher" => ["@id" => home_url('/#organization')],
        "inLanguage" => $site_lang,
    ];

    $graph = [$org, $website];

    if ($is_list) {
        // Страница-листинг (page-list.php): CollectionPage вместо Article, без BreadcrumbList/FAQ.
        $has_part = [];
        foreach ($GLOBALS['ut_collectionpage_items'] ?? [] as $item) {
            $has_part[] = [
                "@type" => "WebPage",
                "url"   => $item['url'],
                "name"  => $item['name'],
            ];
        }

        $collection = [
            "@type" => "CollectionPage",
            "@id"   => $webpage_id,
            "url"   => get_permalink(),
            "name"  => $seo_title,
            "description" => $seo_desc,
            "isPartOf" => ["@id" => home_url('/#website')],
            "inLanguage" => $site_lang,
        ];
        if ($has_part) {
            $collection["hasPart"] = $has_part;
        }
        $graph[] = $collection;
    } else {
        // Обычная страница (page.php) или пост (single.php): WebPage + Article (+FAQPage условно),
        // BreadcrumbList — только для постов (is_single()), не для страниц.
        $breadcrumb_items = [];
        if (function_exists('get_my_breadcrumbs_items')) {
            foreach (get_my_breadcrumbs_items() as $i => $item) {
                $breadcrumb_items[] = [
                    "@type" => "ListItem",
                    "position" => $i + 1,
                    "name" => $item['name'],
                    "item" => $item['url'],
                ];
            }
        }
        $has_breadcrumb = is_single() && $breadcrumb_items;

        $webpage = [
            "@type" => "WebPage",
            "@id"   => $webpage_id,
            "url"   => get_permalink(),
            "name"  => $seo_title,
            "description" => $seo_desc,
            "isPartOf" => ["@id" => home_url('/#website')],
            "inLanguage" => $site_lang,
            "datePublished" => get_the_date('c'),
            "dateModified"  => $curr_date,
        ];
        if ($has_breadcrumb) {
            $webpage["breadcrumb"] = ["@id" => get_permalink() . '#breadcrumb'];
        }
        $graph[] = $webpage;

        if ($has_breadcrumb) {
            $graph[] = [
                "@type" => "BreadcrumbList",
                "@id"   => get_permalink() . '#breadcrumb',
                "itemListElement" => $breadcrumb_items,
            ];
        }

        // Подсчет слов для кириллицы
        $content = strip_tags(get_post_field('post_content', $post_id));
        $word_count = count(preg_split('~[^\p{L}\p{N}]+~u', $content, -1, PREG_SPLIT_NO_EMPTY));

        $article_author = UT_SCHEMA_LITERAL_SPEC
            ? ["@type" => "Organization", "name" => $site_name]
            : ["@id" => home_url('/#organization')];

        $graph[] = [
            "@type" => "Article",
            "@id"   => get_permalink() . '#article',
            "headline" => $social_headline,
            "description" => $seo_desc,
            "author" => $article_author,
            "publisher" => ["@id" => home_url('/#organization')],
            "datePublished" => get_the_date('c'),
            "dateModified"  => $curr_date,
            "mainEntityOfPage" => ["@id" => $webpage_id],
            "wordCount" => $word_count,
            "image" => $img_url ? ["@type" => "ImageObject", "url" => $img_url] : null,
        ];

        $faq_entities = ut_extract_faq_entities($post_id);
        if ($faq_entities) {
            $graph[] = [
                "@type" => "FAQPage",
                "@id"   => $webpage_id . '#faq',
                "isPartOf" => ["@id" => $webpage_id],
                "mainEntity" => $faq_entities,
            ];
        }
    }

    $schema = [
        "@context" => "https://schema.org",
        "@graph" => array_values(array_filter($graph))
    ];

    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
}

// Удаляем стандартный title
remove_action('wp_head', '_wp_render_title_tag', 1);
