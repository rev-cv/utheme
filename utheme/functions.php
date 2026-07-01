<?php
remove_action('wp_head', 'wp_generator');
add_filter('style_loader_src',  fn($src) => remove_query_arg('ver', $src));
add_filter('script_loader_src', fn($src) => remove_query_arg('ver', $src));

function my_theme_enqueue_styles()
{
    wp_enqueue_style('my-theme-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');

$inc_dir = get_stylesheet_directory() . '/inc/';

$includes = array(
    'theme-config.php',
    'language-pack.php',
    'setting-admin.php',
    'custom-styles.php',
    'shortcode-post-meta.php',
    'shortcode-related-articles.php',
    'shortcode-more-pages.php',
    'shortcode-breadcrumbs.php',
    "shortcode-inline.php",
    'font-registry.php',
    'mod-google-font.php',
    'mod-seo.php',
    'mod-expert-checked.php',
    'mod-header-box.php',
    'mod_img-lazy.php',
    'mod-toc.php',
    'mod-geo-integration.php',
    'mod_img-walker.php',
    'mod-sitemap.php',
);

foreach ($includes as $file) {
    $filepath = $inc_dir . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    }
}
