<?php
function my_theme_enqueue_styles()
{
    wp_enqueue_style('utheme-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'utheme_enqueue_styles');

$inc_dir = get_stylesheet_directory() . '/inc/';

$includes = array(
    'language-pack.php',
    'setting-admin.php',
    'custom-styles.php',
    'shortcode-post-meta.php',
    'shortcode-articles-with-pagination.php',
    'shortcode-related-articles.php',
    'shortcode-about-autor.php',
    // 'shortcode-duplicate-toc.php',
    'shortcode-breadcrumbs.php',
    'shortcode-sitemap.php',
    'mod-google-font.php',
    'mod-seo.php',
    'mod-expert-checked.php',
    'mod-header-box.php',
    'mod_img-lazy.php',
    'mod-toc-and-plugin.php',
    'mod_img-walker.php',
);

foreach ($includes as $file) {
    $filepath = $inc_dir . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    }
}
