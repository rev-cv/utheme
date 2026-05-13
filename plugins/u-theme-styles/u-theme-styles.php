<?php
/**
 * Plugin Name: U Theme Styles Configurator
 * Description: Редактор переменных в utheme/src/conf.scss для Docker-сборки.
 */

if (!defined('ABSPATH')) exit;

class UThemeConfigurator {

    private string $scss_file;

    // Brand Colors — основная палитра, генерируется через map.get().
    // НЕ трогаем в основном теле файла — пишутся только в MODE блок.
    private array $brand_color_vars = [
        'color-primary-light', 'color-accent-light', 'color-text-light',
        'color-bg-light', 'color-section-light', 'color-H1-light', 'color-border-light',
        'color-primary-dark', 'color-accent-dark', 'color-text-dark',
        'color-bg-dark', 'color-section-dark', 'color-H1-dark', 'color-border-dark',
    ];

    // Status Colors — цвета состояний (success/warning/error/info/callout-txt).
    // Записываются напрямую в переменные conf.scss; MODE блок управляет алиасами неактивной стороны.
    private array $status_color_vars = [
        'color-success-light',     'color-success-dark',
        'color-warning-light',     'color-warning-dark',
        'color-error-light',       'color-error-dark',
        'color-info-light',        'color-info-dark',
        'color-callout-txt-light', 'color-callout-txt-dark',
    ];

    // Конфиг рандомизации — повторяет randomize_theme.py
    private array $random_config = [
        'main-menu'        => ['island', 'aside', 'boring', 'docs', 'newspaper', 'hierarchical'],
        'menu-accent-align' => ['left', 'center', 'right'],
        'footer-menu'    => ['2columns', '4columns'],
        'more-pages'     => ['grid', 'list', 'slider', 'carousel'],
        'toc-menu'       => ['circle', 'number', 'icon', 'tags', 'vertical-rule', 'two-columns', 'underline', 'card-row', 'numbers-right'],
        'stt-icon'       => [
            'chevron-one', 'chevron-two', 'triple-filled-arrow', 'triple-arrow', 
            'double-filled-arrow', 'double-arrow', 'circle-filled-1', 'circle-1', 
            'circle-2', 'arrow-warm', 'arrow-short', 'arrow-big', 'arrow-pin'],
        'is-not-section' => ['true', 'false'],
        'details'        => ['plus', 'arrow'],
        'callout'          => ['default', 'subtle-tinted', 'left-accent-bar', 'solid-filled', 'dashed-outline', 'icon-badge-card', 'minimal-inline'],
        'callout-icon-set' => ['circle', 'shield', 'diamond'],
        'article-card'   => ['default', 'frame', 'slide', 'windows', 'float', 'soft', 'split', 'classic', 'aside', 'overlay', 'blurred', 'type-first', 'editorial', 'clipped'],
        'image-style'    => ['original', 'marginalia', 'slide-up', 'whisper', 'corner-badge', 'brutalist-strip'],
        'table-style'    => ['default', 'minimal', 'classic', 'cards', 'stripes', 'bold', 'outlined', 'dashed', 'tinted', 'editorial'],
        'is-left-align'  => ['true', 'false'],
        'is-border'      => ['true', 'false'],
        'font-vibe'      => [
            'google', 'strict', 'editorial', 'startup', 'space', 'syntax', 'neo-swiss',
            'engineer', 'boutique', 'wisdom', 'noble', 'manuscript', 'brutal',
            'manifesto', 'black-metal', 'raw', 'velocity', 'courtside', 'district',
            'blast', 'industry', 'overdrive', 'organic', 'vintage', 'interface', 'antidesign',
        ],
        'font-size'   => ['16px', '17px', '18px', '19px', '20px', '21px', '22px', '23px', '24px'],
        'style'       => ['luxury', 'minimalist', 'vibrant', 'bold-dark', 'graphite', 'pastoral', 'japane', 'neon', 'ocean', 'sunset', 'mono'],
        'radius-vibe' => ['sharp', 'neutral', 'dynamic', 'rounded', 'velocity', 'chess', 'sticker'],
    ];

    // Единый маркер MODE-блока в конце файла.
    // Формат: /* MODE {LABEL} */ ... /* END MODE */
    // Все 6 комбинаций (brand/status × dark-only/both/light-only × auto/manual)
    // используют один паттерн — удаление и обнаружение через единый regex.
    private const MODE_BLOCK_START_PREFIX = '/* MODE ';
    private const MODE_BLOCK_END          = '/* END MODE */';

    public function __construct() {
        $this->scss_file = get_template_directory() . '/src/conf.scss';
        add_action('admin_menu',                          [$this, 'add_menu']);
        add_action('admin_enqueue_scripts',               [$this, 'enqueue_assets']);
        add_action('admin_init',                          [$this, 'handle_save']);
        add_action('admin_init',                          [$this, 'handle_randomize']);
        add_action('wp_ajax_u_theme_preview_colors',      [$this, 'ajax_preview_colors']);
    }

    public function add_menu(): void {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#9CA2A7" d="M8.203 5.447L5.83 6.777l1.329-2.374l-1.33-2.374l2.374 1.33l2.374-1.33l-1.33 2.374l1.33 2.374zm11.394 9.305l2.374-1.329l-1.33 2.374l1.33 2.373l-2.374-1.329l-2.374 1.33l1.33-2.374l-1.33-2.374zm2.374-12.724l-1.33 2.374l1.33 2.374l-2.374-1.33l-2.374 1.33l1.33-2.374l-1.33-2.374l2.374 1.33zm-8.223 10.236l2.317-2.316l-2.013-2.013l-2.317 2.317zm.978-5.212l2.222 2.222c.37.35.37.968 0 1.338L5.867 21.694c-.37.37-.987.37-1.339 0l-2.222-2.221c-.37-.352-.37-.969 0-1.34l11.081-11.08c.37-.37.988-.37 1.34 0z"/></svg>';
        add_menu_page('U Theme Styles', 'U Theme Styles', 'manage_options', 'u-theme-styles', [$this, 'render_page'], 'data:image/svg+xml;base64,' . base64_encode($svg), 3);
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_u-theme-styles') return;
        wp_enqueue_style('u-theme-admin-css', plugins_url('assets/admin-style.css', __FILE__));
        wp_enqueue_script('u-theme-admin-js', plugins_url('assets/admin-script.js', __FILE__), ['jquery'], null, true);
        wp_localize_script('u-theme-admin-js', 'uThemeData', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('u_theme_preview_colors'),
            'isManual'  => $this->is_manual_mode(),
            'themeMode' => $this->get_theme_mode(),
        ]);
    }

    // Текущий режим темы: читаем $theme-mode из основного тела файла (без MODE-блока).
    private function get_theme_mode(): string {
        if (!file_exists($this->scss_file)) return 'both';
        $content  = file_get_contents($this->scss_file);
        $stripped = $this->remove_mode_block($content);
        if (preg_match('/^\$theme-mode:\s*["\']?([\w-]+)["\']?;/m', $stripped, $m)) {
            return $m[1];
        }
        return 'both';
    }

    // Ручной режим = MODE-блок в файле содержит "+ MANUAL".
    private function is_manual_mode(): bool {
        if (!file_exists($this->scss_file)) return false;
        $content = file_get_contents($this->scss_file);
        return str_contains($content, self::MODE_BLOCK_START_PREFIX) &&
               str_contains($content, '+ MANUAL');
    }

    // Читаем значения для формы.
    // MODE-блок НЕ стрипим — он содержит hex-значения Brand Colors в manual-режимах.
    // Пропускаем: map.get-выражения (из основного тела) и SCSS-ссылки ($var) из MODE-блока.
    // preg_match_all идёт сверху вниз: последнее присвоение wins — MODE-блок всегда в конце.
    private function get_current_values(): array {
        if (!file_exists($this->scss_file)) return [];
        $content = file_get_contents($this->scss_file);
        $values  = [];

        preg_match_all('/^\$([\w-]+):\s*([^;]+);/m', $content, $matches);

        foreach ($matches[1] as $i => $key) {
            $val = trim($matches[2][$i]);
            if (str_contains($val, 'map.get')) continue;  // авто-генерируемые brand colors
            if (str_starts_with($val, '$'))    continue;  // SCSS-ссылки из MODE-блока
            $values[$key] = trim($val, '"\'');
        }

        return $values;
    }

    public function handle_save(): void {
        if (!isset($_POST['u_save_scss']) || !check_admin_referer('u_theme_update')) return;

        $fields      = $_POST['u_fields'] ?? [];
        $manual_mode = isset($_POST['u_color_mode']) && $_POST['u_color_mode'] === 'manual';
        $theme_mode  = in_array($_POST['u_theme_mode'] ?? '', ['dark-only', 'light-only'], true)
                       ? $_POST['u_theme_mode'] : 'both';
        $content     = file_get_contents($this->scss_file);

        // Валидация: в ручном режиме проверяем Brand Colors активной стороны
        if ($manual_mode) {
            $to_validate = match($theme_mode) {
                'dark-only'  => array_filter($this->brand_color_vars, fn($v) => str_ends_with($v, '-dark')),
                'light-only' => array_filter($this->brand_color_vars, fn($v) => str_ends_with($v, '-light')),
                default      => $this->brand_color_vars,
            };
            $missing = array_filter($to_validate, fn($c) => empty($fields[$c]));
            if (!empty($missing)) {
                add_settings_error('u_theme', 'missing_colors',
                    'Ошибка: не заданы цвета: ' . implode(', ', array_values($missing)), 'error');
                return;
            }
        }

        // Шаг 1: удаляем существующий MODE-блок (все варианты единым regex).
        $content = $this->remove_mode_block($content);

        // Шаг 2: обновляем НЕ-Brand-Color переменные в основном теле файла.
        // Brand Colors ($brand_color_vars) — не трогаем, там map.get-выражения.
        // Status Colors — записываем напрямую (они не в brand_color_vars).
        $string_vars = [
            'main-menu', 'footer-menu', 'toc-menu', 'details', 'article-card', 'more-pages',
            'table-style', 'image-style',
            'font-vibe', 'radius-vibe', 'style', 'toc-icon', 'stt-icon',
            'is-menu-title', 'is-not-section', 'toc-show-title', 'is-left-align', 'is-border',
            'breadcrumbs-separator', 'menu-accent-align',
            'callout', 'callout-icon-set',
            'theme-mode',
            'stt-ghost',
        ];

        // Чекбоксы: если не отмечен — браузер не отправляет поле вовсе.
        // Принудительно выставляем 'false' для всех булевых переменных,
        // которые отсутствуют в $_POST['u_fields'].
        $bool_vars = ['is-menu-title', 'is-not-section', 'toc-show-title', 'is-left-align', 'is-border', 'stt-ghost'];
        foreach ($bool_vars as $bvar) {
            if (!isset($fields[$bvar])) {
                $fields[$bvar] = 'false';
            }
        }

        // Типографические параметры с единицей em: слайдер присылает число, пишем с суффиксом.
        $em_vars = ['hd-height', 'hd-letter-spacing', 'txt-height', 'txt-letter-spacing'];
        foreach ($em_vars as $var) {
            if (isset($fields[$var]) && !str_ends_with($fields[$var], 'em')) {
                $fields[$var] = $fields[$var] . 'em';
            }
        }

        // Параметры с единицей px: слайдер присылает число, пишем с суффиксом.
        $px_vars = ['max-width'];
        foreach ($px_vars as $var) {
            if (isset($fields[$var]) && !str_ends_with($fields[$var], 'px')) {
                $fields[$var] = $fields[$var] . 'px';
            }
        }

        // theme-mode пишется через string_vars — подставляем в $fields чтобы попасть в foreach ниже.
        $fields['theme-mode'] = $theme_mode;

        // hd-case и hd-italic принимают только CSS-ключевые слова (без кавычек в SCSS).
        if (isset($fields['hd-case']) && !in_array($fields['hd-case'], ['none', 'uppercase', 'lowercase'], true)) {
            $fields['hd-case'] = 'none';
        }
        if (isset($fields['hd-italic']) && !in_array($fields['hd-italic'], ['normal', 'italic'], true)) {
            $fields['hd-italic'] = 'normal';
        }

        foreach ($fields as $key => $value) {
            // Brand Colors — пропускаем, они пишутся только в MODE-блок
            if (in_array($key, $this->brand_color_vars)) continue;

            $formatted = in_array($key, $string_vars) ? '"' . $value . '"' : $value;

            // Lookahead (?![\w-]): $color-text не совпадёт с $color-text-light
            // preg_replace_callback: значение подставляется напрямую, без риска
            // интерпретации $1, \\ как backreferences строки замены
            $pattern = '/(\$' . preg_quote($key, '/') . '(?![\w-]))(\s*:\s*)([^;]+)(;)/m';
            $content  = preg_replace_callback($pattern, fn($m) => $m[1] . $m[2] . $formatted . $m[4], $content);
        }

        // Шаг 3: строим и дописываем MODE-блок (пустая строка = режим both+auto, блок не нужен).
        $mode_block = $this->build_mode_block($theme_mode, $manual_mode, $fields);
        if ($mode_block !== '') {
            $content .= $mode_block;
        }

        file_put_contents($this->scss_file, $content);

        // Save site classification options (WP options, not SCSS vars)
        if (isset($_POST['site_stream'])) {
            update_option('site_stream', sanitize_text_field($_POST['site_stream']));
        }
        if (isset($_POST['site_subject'])) {
            update_option('site_subject', sanitize_text_field($_POST['site_subject']));
        }

        add_settings_error('u_theme', 'saved', 'Настройки сохранены. Docker запустил пересборку!', 'updated');
    }

    private function get_callout_icon_set_names(): array {
        $file = get_template_directory() . '/src/scheme.icons.callouts.scss';
        if (!file_exists($file)) return ['circle', 'shield', 'diamond'];
        $sets = [];
        foreach (explode("\n", file_get_contents($file)) as $line) {
            if (preg_match("/^\s+'([\w-]+)':\s*\(\s*$/", $line, $m)) {
                $sets[] = $m[1];
            }
        }
        return $sets ?: ['circle', 'shield', 'diamond'];
    }

    public function handle_randomize(): void {
        if (!isset($_POST['u_randomize_scss']) || !check_admin_referer('u_theme_update')) return;
        if (!file_exists($this->scss_file)) return;

        $this->random_config['callout-icon-set'] = $this->get_callout_icon_set_names();

        $content     = file_get_contents($this->scss_file);
        $string_vars = [
            'main-menu', 'footer-menu', 'toc-menu', 'details', 'article-card', 'more-pages', 'table-style', 'image-style',
            'font-vibe', 'style', 'is-not-section', 'is-left-align', 'is-border',
            'font-size', 'stt-icon', 'menu-accent-align',
            'callout', 'callout-icon-set',
        ];

        // Переменные-списки
        foreach ($this->random_config as $var => $options) {
            $new_val   = $options[array_rand($options)];
            $formatted = in_array($var, $string_vars) ? '"' . $new_val . '"' : $new_val;
            $pattern   = '/(\$' . preg_quote($var, '/') . '(?![\w-]))(\s*:\s*)([^;]+)(;)/m';
            $content   = preg_replace_callback($pattern, fn($m) => $m[1] . $m[2] . $formatted . $m[4], $content);
        }

        // Числовые диапазоны
        $new_seed    = random_int(0, 360);
        $density_raw = random_int(0, 20); // 20 шагов по 0.05 от 0.5 до 1.5
        $new_density = round(0.5 + $density_raw * 0.05, 2);

        foreach (['seed-hue' => $new_seed, 'density-factor' => $new_density] as $var => $new_val) {
            $pattern = '/(\$' . preg_quote($var, '/') . '(?![\w-]))(\s*:\s*)([^;]+)(;)/m';
            $content = preg_replace_callback($pattern, fn($m) => $m[1] . $m[2] . $new_val . $m[4], $content);
        }

        file_put_contents($this->scss_file, $content);
        add_settings_error('u_theme', 'randomized', 'Тема рандомизирована! Docker запустил пересборку.', 'updated');
    }

    // Удаляем любой MODE-блок (все 5 вариантов): /* MODE ... */ ... /* END MODE */
    private function remove_mode_block(string $content): string {
        $start   = preg_quote(self::MODE_BLOCK_START_PREFIX, '/');
        $end     = preg_quote(self::MODE_BLOCK_END, '/');
        $pattern = '/\n*' . $start . '.*?' . $end . '\n*/s';
        return preg_replace($pattern, "\n", $content);
    }

    // Строим MODE-блок для одного из 6 режимов:
    //   both  + auto   → '' (блок не нужен, дефолт)
    //   dark  + auto   → /* MODE DARK ONLY */
    //   light + auto   → /* MODE LIGHT ONLY */
    //   both  + manual → /* MODE BOTH + MANUAL */
    //   dark  + manual → /* MODE DARK ONLY + MANUAL */
    //   light + manual → /* MODE LIGHT ONLY + MANUAL */
    private function build_mode_block(string $theme_mode, bool $manual_mode, array $fields): string {
        if ($theme_mode === 'both' && !$manual_mode) return '';

        $brand_keys  = ['primary', 'accent', 'text', 'bg', 'section', 'H1', 'border'];
        $status_keys = ['success', 'warning', 'error', 'info', 'callout-txt'];

        $label = match(true) {
            $theme_mode === 'dark-only'  && !$manual_mode => 'DARK ONLY',
            $theme_mode === 'light-only' && !$manual_mode => 'LIGHT ONLY',
            $theme_mode === 'both'       && $manual_mode  => 'BOTH + MANUAL',
            $theme_mode === 'dark-only'  && $manual_mode  => 'DARK ONLY + MANUAL',
            default                                       => 'LIGHT ONLY + MANUAL',
        };

        $block = "\n\n" . self::MODE_BLOCK_START_PREFIX . "{$label} */\n";

        if ($manual_mode) {
            if ($theme_mode === 'dark-only') {
                // Brand Colors: тёмные hex → потом light = dark
                foreach ($brand_keys as $key) {
                    $val    = $fields["color-{$key}-dark"] ?? '#000000';
                    $block .= "\$color-{$key}-dark: {$val};\n";
                }
                foreach ($brand_keys  as $key) { $block .= "\$color-{$key}-light: \$color-{$key}-dark;\n"; }
                // Status Colors: light = dark (dark берётся из прямых переменных conf.scss)
                foreach ($status_keys as $key) { $block .= "\$color-{$key}-light: \$color-{$key}-dark;\n"; }

            } elseif ($theme_mode === 'light-only') {
                // Brand Colors: светлые hex → потом dark = light
                foreach ($brand_keys as $key) {
                    $val    = $fields["color-{$key}-light"] ?? '#ffffff';
                    $block .= "\$color-{$key}-light: {$val};\n";
                }
                foreach ($brand_keys  as $key) { $block .= "\$color-{$key}-dark: \$color-{$key}-light;\n"; }
                // Status Colors: dark = light
                foreach ($status_keys as $key) { $block .= "\$color-{$key}-dark: \$color-{$key}-light;\n"; }

            } else {
                // both + manual: оба варианта hex для Brand Colors
                foreach ($brand_keys as $key) {
                    $vl     = $fields["color-{$key}-light"] ?? '#ffffff';
                    $vd     = $fields["color-{$key}-dark"]  ?? '#000000';
                    $block .= "\$color-{$key}-light: {$vl};\n";
                    $block .= "\$color-{$key}-dark: {$vd};\n";
                }
            }
        } else {
            // Auto-режимы: только алиасы (hex в conf.scss, SCSS делает остальное)
            if ($theme_mode === 'dark-only') {
                foreach ($brand_keys  as $key) { $block .= "\$color-{$key}-light: \$color-{$key}-dark;\n"; }
                foreach ($status_keys as $key) { $block .= "\$color-{$key}-light: \$color-{$key}-dark;\n"; }
            } else {
                foreach ($brand_keys  as $key) { $block .= "\$color-{$key}-dark: \$color-{$key}-light;\n"; }
                foreach ($status_keys as $key) { $block .= "\$color-{$key}-dark: \$color-{$key}-light;\n"; }
            }
        }

        $block .= self::MODE_BLOCK_END . "\n";
        return $block;
    }

    public function render_page(): void {
        $v           = $this->get_current_values();
        $manual_mode = $this->is_manual_mode();
        $theme_mode  = $this->get_theme_mode();
        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }

    // ── AJAX: вернуть рассчитанные цвета для ручного режима ──────────────────
    public function ajax_preview_colors(): void {
        check_ajax_referer('u_theme_preview_colors', 'nonce');
        $vals  = $this->get_current_values();
        $hue   = (int)($vals['seed-hue'] ?? 214);
        $style = $vals['style'] ?? 'graphite';
        wp_send_json_success($this->generate_color_scheme($hue, $style));
    }

    // ── Генерация цветовой схемы — зеркало scheme.color.scss ─────────────────
    private function generate_color_scheme(int $hue, string $style): array {
        $sat = $style === 'luxury' ? 35.0
             : (in_array($style, ['neon', 'japane', 'vibrant'], true) ? 95.0 : 65.0);
        $lgt = $style === 'luxury' ? 35.0 : 50.0;

        $p_gen = $this->hsl_to_hex($hue, $sat, $lgt);
        $a_gen = $this->hsl_to_hex(fmod($hue + 150, 360), $sat, $lgt);

        $bg_l = '#ffffff'; $tx_l = '#1a1a1a';
        $pr_l = $p_gen;    $ac_l = $a_gen;
        $bg_d = '#0d0d0d'; $tx_d = '#f0f0f0';
        $pr_d = $this->color_adjust($p_gen, 15);
        $ac_d = null;

        switch ($style) {
            case 'luxury':
                $bg_l = $this->color_mix($p_gen, '#FAFAF8', 3);
                $tx_l = $this->color_mix($p_gen, '#1A1A1A', 15);
                $bg_d = $this->color_mix($p_gen, '#0D0D0D', 12);
                break;
            case 'bold-dark':
                $bg_l = '#121212'; $tx_l = '#e0e0e0';
                $pr_l = $this->hsl_to_hex($hue, 85, 60);
                $bg_d = '#000000';
                break;
            case 'graphite':
                $bg_l = '#F9F9F9';
                $tx_l = $this->hsl_to_hex($hue, 15, 20);
                $pr_l = $this->hsl_to_hex($hue, 10, 25);
                $bg_d = $this->hsl_to_hex($hue, 8, 12);
                $pr_d = $this->hsl_to_hex($hue, 5, 85);
                break;
            case 'pastoral':
                $bg_l = '#F2EDE4'; $tx_l = '#4A3F35';
                $pr_l = $this->color_mix($p_gen, '#7D6B5D', 60);
                $bg_d = '#35322D'; $tx_d = '#D9D2C5';
                break;
            case 'japane':
                $bg_l = '#FDFCF0'; $tx_l = '#080808'; $pr_l = '#E63946';
                $bg_d = '#050505';
                $pr_d = $this->hsl_to_hex($hue, 100, 60);
                $ac_d = $this->hsl_to_hex(fmod($hue + 60, 360), 100, 55);
                break;
            case 'minimalist':
                $bg_l = '#ffffff'; $pr_l = '#111111'; $ac_l = $p_gen;
                $bg_d = '#050505'; $pr_d = '#ffffff';
                break;
            case 'neon':
                $bg_l = '#0a0a0f'; $tx_l = '#e8e8ff';
                $pr_l = $this->hsl_to_hex($hue, 100, 60);
                $bg_d = '#000000';
                $ac_l = $this->hsl_to_hex(fmod($hue + 120, 360), 100, 55);
                break;
            case 'ocean':
                $bg_l = '#F0F4F8';
                $tx_l = $this->hsl_to_hex($hue, 25, 18);
                $pr_l = $this->hsl_to_hex($hue, 55, 40);
                $bg_d = $this->hsl_to_hex($hue, 20, 10);
                $pr_d = $this->hsl_to_hex($hue, 45, 70);
                $ac_l = $this->hsl_to_hex(fmod($hue - 30 + 360, 360), 60, 45);
                break;
            case 'sunset':
                $warm = fmod($hue + 20, 360);
                $bg_l = '#FFF8F2';
                $tx_l = $this->hsl_to_hex(20, 30, 20);
                $pr_l = $this->hsl_to_hex($warm, 70, 45);
                $bg_d = $this->hsl_to_hex(20, 15, 10);
                $pr_d = $this->hsl_to_hex($warm, 65, 65);
                $ac_l = $this->hsl_to_hex(fmod($warm + 40, 360), 80, 50);
                break;
            case 'mono':
                $bg_l = $this->hsl_to_hex($hue, 10, 97);
                $tx_l = $this->hsl_to_hex($hue, 20, 15);
                $pr_l = $this->hsl_to_hex($hue, 40, 35);
                $bg_d = $this->hsl_to_hex($hue, 15, 8);
                $pr_d = $this->hsl_to_hex($hue, 30, 75);
                $ac_l = $this->hsl_to_hex($hue, 60, 50);
                break;
        }

        $ac_d = $ac_d ?? $this->color_adjust($ac_l, 10);
        $h1_l = ($style === 'bold-dark' || $style === 'neon') ? '#ffffff' : $pr_l;

        return [
            'color-primary-light' => $pr_l,
            'color-accent-light'  => $ac_l,
            'color-text-light'    => $tx_l,
            'color-bg-light'      => $bg_l,
            'color-section-light' => $this->color_adjust($bg_l, -4),
            'color-H1-light'      => $h1_l,
            'color-border-light'  => $this->color_mix($pr_l, $bg_l, 15),
            'color-primary-dark'  => $pr_d,
            'color-accent-dark'   => $ac_d,
            'color-text-dark'     => $tx_d,
            'color-bg-dark'       => $bg_d,
            'color-section-dark'  => $this->color_adjust($bg_d, 6),
            'color-H1-dark'       => $pr_d,
            'color-border-dark'   => $this->color_mix($pr_d, $bg_d, 20),
        ];
    }

    // ── Цветовые утилиты (HSL ↔ RGB ↔ hex) ───────────────────────────────────
    private function hsl_to_hex(float $h, float $s, float $l): string {
        $s /= 100; $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        if      ($h < 60)  { $r = $c; $g = $x; $b = 0; }
        elseif  ($h < 120) { $r = $x; $g = $c; $b = 0; }
        elseif  ($h < 180) { $r = 0;  $g = $c; $b = $x; }
        elseif  ($h < 240) { $r = 0;  $g = $x; $b = $c; }
        elseif  ($h < 300) { $r = $x; $g = 0;  $b = $c; }
        else                { $r = $c; $g = 0;  $b = $x; }
        return sprintf('#%02x%02x%02x',
            (int)round(($r + $m) * 255),
            (int)round(($g + $m) * 255),
            (int)round(($b + $m) * 255));
    }

    private function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function rgb_to_hsl(int $r, int $g, int $b): array {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r, $g, $b); $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) return [0, 0, $l * 100];
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if      ($max === $r) $h = fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6);
        elseif  ($max === $g) $h = ($b - $r) / $d + 2;
        else                  $h = ($r - $g) / $d + 4;
        return [$h * 60, $s * 100, $l * 100];
    }

    private function color_adjust(string $hex, float $delta): string {
        [$r, $g, $b] = $this->hex_to_rgb($hex);
        [$h, $s, $l] = $this->rgb_to_hsl($r, $g, $b);
        return $this->hsl_to_hex($h, $s, max(0, min(100, $l + $delta)));
    }

    private function color_mix(string $hex1, string $hex2, float $weight): string {
        [$r1, $g1, $b1] = $this->hex_to_rgb($hex1);
        [$r2, $g2, $b2] = $this->hex_to_rgb($hex2);
        $w = $weight / 100;
        return sprintf('#%02x%02x%02x',
            (int)round($r1 * $w + $r2 * (1 - $w)),
            (int)round($g1 * $w + $g2 * (1 - $w)),
            (int)round($b1 * $w + $b2 * (1 - $w)));
    }
}

new UThemeConfigurator();
