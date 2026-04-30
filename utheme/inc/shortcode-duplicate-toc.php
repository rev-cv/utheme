<?php

function custom_toc_copy_shortcode()
{
    $content = do_blocks(get_the_content());
    return build_toc_html($content);
}

add_shortcode('duplicate_toc', 'custom_toc_copy_shortcode');
