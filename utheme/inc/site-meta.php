<?php
/**
 * Portable: include this file anywhere (theme, plugin, mu-plugin) to register
 * two site-level options — site_stream and site_subject — and surface them as
 * select fields in WP Admin → Settings → General (Site Classification section).
 */

defined('ABSPATH') || exit;

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

    add_settings_section('utheme_site_meta', 'Site Classification', '__return_false', 'general');

    add_settings_field('site_stream',  'Stream of Site',  'utheme_render_site_stream_field',  'general', 'utheme_site_meta');
    add_settings_field('site_subject', 'Subject of Site', 'utheme_render_site_subject_field', 'general', 'utheme_site_meta');
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
