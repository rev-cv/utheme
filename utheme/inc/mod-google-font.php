<?php
function my_theme_enqueue_fonts() {
    $active_vibe = my_theme_get_config('font-vibe', 'strict');
    $registry    = u_font_registry();

    if (!empty($registry[$active_vibe]['gf'])) {
        wp_enqueue_style('google-fonts', $registry[$active_vibe]['gf'], [], null, 'print');
    }
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_fonts');
