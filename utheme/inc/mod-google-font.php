<?php
function my_theme_enqueue_fonts()
{
    $active_vibe = my_theme_get_config('font-vibe', 'strict');

    $font_map = [
        'google'    => 'https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap',
        'strict'    => 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap',
        'brutal'    => 'https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:ital,wght@0,100..900;1,100..900&display=swap',
        'editorial' => 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap',
        'startup'   => 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap',
        'space'     => 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap',
        'syntax'    => 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap',
        'neo-swiss' => 'https://fonts.googleapis.com/css2?family=Arimo:ital,wght@0,400..700;1,400..700&family=Heebo:wght@100..900&display=swap',
        'vogue'     => 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap',
        'boutique'  => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap',
        'wisdom'    => 'https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&family=Fauna+One&display=swap',
        'noble'     => 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&family=Manrope:wght@200..800&display=swap',
        'noble'     => 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&family=Manrope:wght@200..800&display=swap',
        'urban'     => 'https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:ital,wght@0,100..900;1,100..900&display=swap',
        'manifesto' => 'https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
        'black-metal' => 'https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&family=Unbounded:wght@200..900&display=swap',
        'raw'       => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap',
        'organic'   => 'https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap',
        'vintage'   => 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=DM+Serif+Display:ital@0;1&display=swap',
        'velocity'  => 'https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&family=Red+Hat+Display:ital,wght@0,300..900;1,300..900&display=swap',
        'courtside' => 'https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@0,100..900;1,100..900&family=Red+Hat+Display:ital,wght@0,300..900;1,300..900&display=swap',
        'district'  => 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Ruslan+Display&display=swap',
        'blast'     => 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Rubik+Mono+One&display=swap',
        'industry'  => 'https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap',
        'interface' => 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap',
        'manuscript' => 'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap',
        
    ];

    if (isset($font_map[$active_vibe])) {
        // 1. Добавляем preconnect через фильтр (встроится в head выше стилей)
        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ($relation_type === 'preconnect') {
                $urls[] = 'https://fonts.googleapis.com';
                $urls[] = [
                    'href' => 'https://fonts.gstatic.com',
                    'crossorigin',
                ];
            }
            return $urls;
        }, 10, 2);

        // 2. Подключаем сами шрифты
        $url = "https://fonts.googleapis.com/css2?family=" . $font_map[$active_vibe] . "&display=swap";
        wp_enqueue_style('google-fonts', $url, [], null);
    }


    if (isset($font_map[$active_vibe]) && !empty($font_map[$active_vibe])) {
        wp_enqueue_style(
            'google-fonts',
            $font_map[$active_vibe],
            [],
            null
        );
    }
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_fonts');
