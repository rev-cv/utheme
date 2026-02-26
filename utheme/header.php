<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body>
    <?php
        $menu_type = my_theme_get_config('main-menu', 'island');

        if ($menu_type === 'aside' || $menu_type === 'boring') {
            get_template_part('components/main-menu-new-aside');
        } elseif ($menu_type === 'marquee') {
            get_template_part('components/main-menu-marquee');
        } elseif ($menu_type === 'docs') {
            get_template_part('components/main-menu-docs');
        }else {
            get_template_part('components/main-menu-island');
        }
    ?>
