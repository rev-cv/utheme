<?php
/**
 * Plugin Name: Sonar
 * Description: Классификация сайта (Settings → General → Site Classification) + скрытый идентификационный эндпойнт /ping-site.
 */

if (!defined('ABSPATH')) exit;

// ── Реальная идентичность темы ──────────────────────────────────────────────
// Обфускация (core/theme_identity.py + provision.sh.j2) патчит Theme Name /
// Author в style.css скопированной темы — реальные значения нигде на сайте
// больше не хранятся. В отличие от обфусцированного имени (читается живьём
// через wp_get_theme() — его редактировать вручную не нужно), реальные имя
// и версию темы заполняет pipeline при деплое (core/docker_setup.py:
// configure_theme_identity()) — имя всегда "UTheme", версия парсится из
// CHANGELOG.md. Плагин здесь ничего не подставляет по умолчанию (пусто,
// пока pipeline или админ явно не запишут значение) — это просто поля
// для чтения/редактирования того, что уже сохранено.

// ── /sonar-ping ──────────────────────────────────────────────────────────
// Скрытый эндпойнт для программной идентификации сайтов пайплайна utheme —
// нужен, т.к. тема на каждом сайте обфусцируется по-разному и её больше
// нельзя опознать по имени/слагу. Без верного ключа в заголовке эндпойнт
// неотличим от обычного 404 (используется настоящий 404 темы через
// $wp_query->set_404(), а не отдельная "поддельная" страница).
//
// Ключ общий для всех сайтов пайплайна (сознательный выбор: не хранить .env
// с секретом на каждом сайте отдельно — риск, что он не запишется/потеряется
// и сайт перестанет опознаваться, важнее гипотетической компрометации одного
// секрета сразу для всех — целевая угроза здесь: автоматические скринеры,
// а не целенаправленная атака на конкретный сайт).
const UTHEME_PING_PATH   = '/sonar-ping';
const UTHEME_PING_HEADER = 'HTTP_X_SONAR_KEY'; // header: X-Sonar-Key
const UTHEME_PING_KEY    = 'bet-access-site';

function utheme_ping_endpoint(): void {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (rtrim((string) $path, '/') !== UTHEME_PING_PATH) {
        return;
    }

    $provided = $_SERVER[UTHEME_PING_HEADER] ?? '';
    if (!hash_equals(UTHEME_PING_KEY, (string) $provided)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    $active_plugins = array_map(
        fn($path) => dirname($path),
        (array) get_option('active_plugins', [])
    );

    $query_args_base = [
        'numberposts'      => -1,
        'suppress_filters' => true,
    ];

    $published       = get_posts($query_args_base + ['post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    $scheduled       = get_posts($query_args_base + ['post_type' => 'post', 'post_status' => 'future',  'orderby' => 'date', 'order' => 'ASC']);
    $published_pages = get_posts($query_args_base + ['post_type' => 'page', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    $scheduled_pages  = get_posts($query_args_base + ['post_type' => 'page', 'post_status' => 'future',  'orderby' => 'date', 'order' => 'ASC']);

    $to_summary = fn($p) => [
        'id'    => $p->ID,
        'title' => get_the_title($p),
        'url'   => get_permalink($p),
        'date'  => get_gmt_from_date($p->post_date, 'Y-m-d\TH:i:s') . 'Z',
    ];

    $theme = wp_get_theme();

    status_header(200);
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode([
        'site_name'             => get_bloginfo('name'),
        'locale'                => get_locale(),
        'theme_real_name'       => get_option('utheme_real_theme_name', ''),
        'theme_real_version'    => get_option('utheme_real_theme_version', ''),
        'theme_obfuscated_name' => $theme->get('Name'),
        'site'                  => home_url(),
        'server'                => [
            'ip'          => $_SERVER['SERVER_ADDR'] ?? null,
            'time_utc'    => gmdate('Y-m-d\TH:i:s\Z'),
            'wp_timezone' => wp_timezone_string(),
        ],
        'classification' => [
            'stream'  => get_option('site_stream', ''),
            'subject' => get_option('site_subject', ''),
        ],
        'environment' => [
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'active_plugins' => array_values($active_plugins),
        ],
        'content' => [
            'published_count'       => count($published),
            'last_published_at'     => $published ? $to_summary($published[0])['date'] : null,
            'published_posts'       => array_map($to_summary, $published),
            'scheduled_posts'       => array_map($to_summary, $scheduled),
            'published_pages_count' => count($published_pages),
            'published_pages'       => array_map($to_summary, $published_pages),
            'scheduled_pages'       => array_map($to_summary, $scheduled_pages),
        ],
    ]);
    exit;
}
add_action('template_redirect', 'utheme_ping_endpoint');

// ── Settings API: Site Classification (Settings → General) ─────────────────
// Заменяет собой utheme/inc/site-meta.php (тема) и вкладку Site Info в
// плагине u-theme-styles — единая точка редактирования этих полей.

function utheme_site_meta_streams(): array {
    return [
        'stream-1' => 'Stream 1',
        'stream-2' => 'Stream 2',
        'stream-3' => 'Stream 3',
        'stream-4' => 'Stream 4',
    ];
}

function utheme_site_meta_subjects(): array {
    return [
        'alpine_skiing', 'american_footbal', 'aussie_rules', 'badminton',
        'baseball', 'basketball', 'biathlon', 'boxing', 'cricket',
        'crosscountry', 'cycling', 'darts', 'english', 'esports',
        'field_hockey', 'floorball', 'football', 'formula1', 'golf',
        'greyhound_racing', 'handball', 'hockey', 'horse_racing', 'ice_hockey',
        'mma', 'motorsport', 'padel', 'rugby', 'rugby_league', 'snooker',
        'squash', 'table_tennis', 'tennis', 'volleyball', 'waterpolo',
    ];
}

function utheme_register_site_meta_settings(): void {
    register_setting('general', 'site_stream', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('general', 'site_subject', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('general', 'utheme_html_lang', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('general', 'utheme_html_lang_enabled', [
        'type'              => 'string',
        'sanitize_callback' => fn($v) => $v === '1' ? '1' : '0',
        'default'           => '0',
    ]);

    register_setting('general', 'utheme_real_theme_name', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('general', 'utheme_real_theme_version', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);

    add_settings_section('utheme_site_meta', 'Site Classification', '__return_false', 'general');

    add_settings_field('site_stream',  'Stream of Site',  'utheme_render_site_stream_field',  'general', 'utheme_site_meta');
    add_settings_field('site_subject', 'Subject of Site', 'utheme_render_site_subject_field', 'general', 'utheme_site_meta');
    add_settings_field('utheme_html_lang', 'HTML Lang (frontend)', 'utheme_render_html_lang_field', 'general', 'utheme_site_meta');

    add_settings_section('utheme_theme_identity', 'Theme Identity', '__return_false', 'general');

    add_settings_field('utheme_real_theme_name',    'Real Theme Name',    'utheme_render_real_theme_name_field',    'general', 'utheme_theme_identity');
    add_settings_field('utheme_real_theme_version', 'Real Theme Version', 'utheme_render_real_theme_version_field', 'general', 'utheme_theme_identity');
}
add_action('admin_init', 'utheme_register_site_meta_settings');

function utheme_render_site_stream_field(): void {
    $current = get_option('site_stream', '');
    echo '<select name="site_stream" id="site_stream">';
    echo '<option value="">— not set —</option>';
    foreach (utheme_site_meta_streams() as $val => $label) {
        printf('<option value="%s"%s>%s</option>',
            esc_attr($val), selected($current, $val, false), esc_html($label));
    }
    echo '</select>';
}

function utheme_render_site_subject_field(): void {
    $current = get_option('site_subject', '');
    echo '<select name="site_subject" id="site_subject">';
    echo '<option value="">— not set —</option>';
    foreach (utheme_site_meta_subjects() as $subject) {
        $label = ucwords(str_replace('_', ' ', $subject));
        printf('<option value="%s"%s>%s</option>',
            esc_attr($subject), selected($current, $subject, false), esc_html($label));
    }
    echo '</select>';
}

function utheme_render_real_theme_name_field(): void {
    $current = get_option('utheme_real_theme_name', '');
    printf(
        '<input type="text" name="utheme_real_theme_name" value="%s" style="width:220px">',
        esc_attr($current)
    );
}

function utheme_render_real_theme_version_field(): void {
    $current = get_option('utheme_real_theme_version', '');
    printf(
        '<input type="text" name="utheme_real_theme_version" value="%s" style="width:100px">',
        esc_attr($current)
    );
}

function utheme_render_html_lang_field(): void {
    $enabled         = get_option('utheme_html_lang_enabled', '0');
    $custom          = get_option('utheme_html_lang', '');
    $wp_default_lang = get_bloginfo('language');
    ?>
    <label>
        <input type="hidden" name="utheme_html_lang_enabled" value="0">
        <input type="checkbox" name="utheme_html_lang_enabled" value="1" <?php checked($enabled, '1'); ?>>
        Переопределять locale для фронтенда
    </label>
    <p>
        <input type="text" name="utheme_html_lang" value="<?php echo esc_attr($custom); ?>"
               placeholder="<?php echo esc_attr($wp_default_lang); ?>" style="width:140px">
        <br>
        <span class="description">
            WP locale для атрибута <code>lang</code> в <code>&lt;html&gt;</code>.
            Примеры: <code>en_GB</code>, <code>fr_BE</code>, <code>pt_BR</code>.
            Пусто или галка снята — WordPress использует стандартное значение
            (<code><?php echo esc_html($wp_default_lang); ?></code>).
        </span>
    </p>
    <?php
}
