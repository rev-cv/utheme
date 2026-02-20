<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    /*
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap" rel="stylesheet">
    */ ?>
    <?php wp_head(); ?>
</head>

<body>
    <?php
    $conf_file = get_template_directory() . '/src/conf.scss';
    $menu_type = 'island'; // Значение по умолчанию

    if (file_exists($conf_file)) {
        $conf_content = file_get_contents($conf_file);
        if (preg_match('/\$main-menu:\s*["\']?(island|aside|marquee)["\']?/', $conf_content, $matches)) {
            $menu_type = $matches[1];
        }
    }

    if ($menu_type === 'aside') {
        get_template_part('components/main-menu-new-aside');
    } elseif ($menu_type === 'marquee') {
        get_template_part('components/main-menu-marquee');
    } else {
        get_template_part('components/main-menu-island');
    }
    ?>