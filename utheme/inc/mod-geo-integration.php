<?php

function my_theme_geo_injection( string $content ): string {

    if ( ! is_single() && ! is_page() ) return $content;
    if ( geo_is_utility_page() ) return $content;

    //   shortcode  → имя шорткода WordPress
    //   postprocess → (необязательно) функция для обработки HTML после рендера
    //
    //   Убрать sports_predictions?    → удали вторую строку.
    //   Добавить новый блок?          → добавь строку по образцу.
    //   Поменять порядок блоков?      → поменяй строки местами.
    //
    $blocks = [
        [
            'shortcode'   => 'geo_info',
            'postprocess' => 'geo_apply_skeleton',  // заменяет спиннер скелетонами (CLS-fix)
        ],
        [
            'shortcode' => 'sports_predictions',
        ],
    ];
    // ─────────────────────────────────────────────────────────────────────────

    $auto_html = '';

    foreach ( $blocks as $block ) {
        $html = geo_render_block( $block );
        if ( $html ) {
            $auto_html .= $html;
        }
    }

    if ( ! $auto_html ) return $content;

    $block = '<div class="dynamic-injection-container">' . $auto_html . '</div>';
    return geo_auto_place( $content, $block );
}

add_filter( 'the_content', 'my_theme_geo_injection', 11 );


// ═══════════════════════════════════════════════════════════════════════════════
// Вспомогательные функции (менять не нужно — только если меняется логика)
// ═══════════════════════════════════════════════════════════════════════════════


/* Рендерит шорткод и применяет postprocess-функцию, если задана. Возвращает пустую строку, если шорткод не зарегистрирован или вернул пусто. */
function geo_render_block( array $block ): string {
    if ( ! shortcode_exists( $block['shortcode'] ) ) return '';

    $html = do_shortcode( '[' . $block['shortcode'] . ']' );
    if ( ! $html ) return '';

    if ( ! empty( $block['postprocess'] ) && is_callable( $block['postprocess'] ) ) {
        $html = call_user_func( $block['postprocess'], $html );
    }

    return $html;
}

/* Вставляет $block сразу после </h1> */
function geo_auto_place( string $content, string $block ): string {
    if ( preg_match( '/<\/h1>/i', $content, $h1_m, PREG_OFFSET_CAPTURE ) ) {
        $after_h1 = $h1_m[0][1] + strlen( $h1_m[0][0] );
        return substr_replace( $content, $block, $after_h1, 0 );
    }

    return $content;
}

/* Проверяет, принадлежит ли текущая страница категории "Utility Pages". Такие страницы исключаются из инжекции */
function geo_is_utility_page(): bool {
    $excluded_ids = get_posts( [
        'post_type'   => 'page',
        'numberposts' => -1,
        'fields'      => 'ids',
        'tax_query'   => [ [
            'taxonomy' => 'category',
            'field'    => 'name',
            'terms'    => 'Utility Pages',
        ] ],
    ] );

    return in_array( get_the_ID(), $excluded_ids );
}

/* CLS-fix для geo_info: после рендера плагин заполнил geo_data. Читаем точное число карточек через рефлексию и заменяем спиннер скелетонами, чтобы страница не прыгала при загрузке */
function geo_apply_skeleton( string $html ): string {
    if ( ! class_exists( 'TC_Sports_Predictions_Pro' ) ) return $html;

    $count = 6;
    try {
        $ref = new \ReflectionProperty( 'TC_Sports_Predictions_Pro', 'geo_data' );
        $ref->setAccessible( true );
        $geo = $ref->getValue( TC_Sports_Predictions_Pro::instance() );
        if ( is_array( $geo ) && isset( $geo['items'] ) ) {
            $count = max( 1, min( count( $geo['items'] ), 20 ) );
        }
    } catch ( \ReflectionException ) { /* оставляем $count = 6 */ }

    $skeletons = str_repeat( '<div class="tc-card-skeleton" aria-hidden="true"></div>', $count );

    return preg_replace(
        '/<div\s+class="tc-loading">[\s\S]*?<\/p>\s*<\/div>/s',
        $skeletons,
        $html,
        1
    );
}
