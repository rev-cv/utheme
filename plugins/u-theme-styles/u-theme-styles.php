<?php
/**
 * Plugin Name: U Theme Styles Configurator
 * Description: Редактор переменных в utheme/src/conf.scss для Docker-сборки.
 */

if (!defined('ABSPATH')) exit;

class UThemeConfigurator {

    private string $scss_file;

    // Цветовые переменные — те что дублируются в ручном блоке.
    // Основной блок в файле (с map.get-выражениями) НЕ ТРОГАЕМ НИКОГДА.
    private array $color_vars = [
        'color-primary-light', 'color-accent-light', 'color-text-light',
        'color-bg-light', 'color-section-light', 'color-H1-light', 'color-border-light',
        'color-primary-dark', 'color-accent-dark', 'color-text-dark',
        'color-bg-dark', 'color-section-dark', 'color-H1-dark', 'color-border-dark',
    ];

    // Конфиг рандомизации — повторяет randomize_theme.py
    private array $random_config = [
        'main-menu'        => ['island', 'aside', 'boring', 'docs', 'newspaper', 'hierarchical'],
        'menu-accent-align' => ['left', 'center', 'right'],
        'footer-menu'    => ['2columns', '4columns'],
        'more-pages'     => ['grid', 'list', 'slider', 'carousel'],
        'toc-menu'       => ['circle', 'number', 'icon', 'tags'],
        'stt-icon'       => [
            'chevron-one', 'chevron-two', 'triple-filled-arrow', 'triple-arrow', 
            'double-filled-arrow', 'double-arrow', 'circle-filled-1', 'circle-1', 
            'circle-2', 'arrow-warm', 'arrow-short', 'arrow-big', 'arrow-pin'],
        'is-not-section' => ['true', 'false'],
        'details'        => ['plus', 'arrow'],
        'article-card'   => ['default', 'frame', 'slide', 'windows', 'float', 'soft', 'split'],
        'is-img_contain' => ['true', 'false'],
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

    // Маркеры ручного блока в конце файла.
    // is_manual_mode() смотрит только на их наличие.
    private const MANUAL_BLOCK_START = '/* Manual Color Configuration */';
    private const MANUAL_BLOCK_END   = '/* End Manual Color Configuration */';

    // Маркеры блока режима темы (dark-only / light-only).
    // Блок всегда идёт после ручного блока цветов.
    private const THEME_MODE_BLOCK_PREFIX = '/* Theme Mode:';
    private const THEME_MODE_BLOCK_END    = '/* End Theme Mode */';

    public function __construct() {
        $this->scss_file = get_template_directory() . '/src/conf.scss';
        add_action('admin_menu',                          [$this, 'add_menu']);
        add_action('admin_enqueue_scripts',               [$this, 'enqueue_assets']);
        add_action('admin_init',                          [$this, 'handle_save']);
        add_action('admin_init',                          [$this, 'handle_randomize']);
        add_action('wp_ajax_u_theme_preview_colors',      [$this, 'ajax_preview_colors']);
    }

    public function add_menu(): void {
        add_theme_page('U Theme Styles', 'U Theme Styles', 'manage_options', 'u-theme-styles', [$this, 'render_page']);
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'appearance_page_u-theme-styles') return;
        wp_enqueue_style('u-theme-admin-css', plugins_url('assets/admin-style.css', __FILE__));
        wp_enqueue_script('u-theme-admin-js', plugins_url('assets/admin-script.js', __FILE__), ['jquery'], null, true);
        wp_localize_script('u-theme-admin-js', 'uThemeData', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('u_theme_preview_colors'),
            'isManual' => $this->is_manual_mode(),
        ]);
    }

    // Текущий режим темы: читаем из маркера блока.
    private function get_theme_mode(): string {
        if (!file_exists($this->scss_file)) return 'both';
        if (preg_match('/\/\* Theme Mode: ([\w-]+) \*\//', file_get_contents($this->scss_file), $m)) {
            return $m[1];
        }
        return 'both';
    }

    // Ручной режим = маркер присутствует в файле. Никакой эвристики.
    private function is_manual_mode(): bool {
        if (!file_exists($this->scss_file)) return false;
        return str_contains(file_get_contents($this->scss_file), self::MANUAL_BLOCK_START);
    }

    // Читаем значения для формы.
    // Цветовые переменные берём из ручного блока (если он есть),
    // остальные — из основного тела файла.
    private function get_current_values(): array {
        if (!file_exists($this->scss_file)) return [];
        $content = file_get_contents($this->scss_file);
        $values  = [];

        preg_match_all('/^\$([\w-]+):\s*([^;]+);/m', $content, $matches);

        foreach ($matches[1] as $i => $key) {
            $val = trim($matches[2][$i]);

            // Пропускаем map.get-выражения из основного блока —
            // они не нужны форме, для цветов читаем только ручной блок.
            if (str_contains($val, 'map.get')) continue;

            // preg_match_all идёт сверху вниз: если переменная встречается дважды
            // (основной блок + ручной блок), второе значение перезапишет первое — это правильно,
            // ручной блок всегда в конце файла и должен иметь приоритет.
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

        // Валидация: в ручном режиме все цвета обязательны
        if ($manual_mode) {
            $missing = array_filter($this->color_vars, fn($c) => empty($fields[$c]));
            if (!empty($missing)) {
                add_settings_error('u_theme', 'missing_colors',
                    'Ошибка: не заданы цвета: ' . implode(', ', array_values($missing)), 'error');
                return;
            }
        }

        // Шаг 1: всегда удаляем оба блока — ручной и режим темы.
        // Они добавятся заново в конце если нужны.
        $content = $this->remove_manual_color_block($content);
        $content = $this->remove_theme_mode_block($content);

        // Шаг 2: обновляем НЕ-цветовые переменные в основном теле файла.
        // Цветовые переменные ($color_vars) — не трогаем в основном теле никогда,
        // там map.get-выражения которые должны оставаться нетронутыми.
        $string_vars = [
            'main-menu', 'footer-menu', 'toc-menu', 'details', 'article-card', 'more-pages',
            'font-vibe', 'radius-vibe', 'style', 'toc-icon', 'stt-icon',
            'is-menu-title', 'is-not-section', 'is-img_contain', 'is-left-align', 'is-border',
            'breadcrumbs-separator', 'menu-accent-align',
        ];

        // Чекбоксы: если не отмечен — браузер не отправляет поле вовсе.
        // Принудительно выставляем 'false' для всех булевых переменных,
        // которые отсутствуют в $_POST['u_fields'].
        $bool_vars = ['is-menu-title', 'is-not-section', 'is-img_contain', 'is-left-align', 'is-border'];
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

        // hd-case и hd-italic принимают только CSS-ключевые слова (без кавычек в SCSS).
        if (isset($fields['hd-case']) && !in_array($fields['hd-case'], ['none', 'uppercase', 'lowercase'], true)) {
            $fields['hd-case'] = 'none';
        }
        if (isset($fields['hd-italic']) && !in_array($fields['hd-italic'], ['normal', 'italic'], true)) {
            $fields['hd-italic'] = 'normal';
        }

        foreach ($fields as $key => $value) {
            // Цветовые переменные — пропускаем полностью, они пишутся только в ручной блок
            if (in_array($key, $this->color_vars)) continue;

            $formatted = in_array($key, $string_vars) ? '"' . $value . '"' : $value;

            // Lookahead (?![\w-]): $color-text не совпадёт с $color-text-light
            // preg_replace_callback: значение подставляется напрямую, без риска
            // интерпретации $1, \\ как backreferences строки замены
            $pattern = '/(\$' . preg_quote($key, '/') . '(?![\w-]))(\s*:\s*)([^;]+)(;)/m';
            $content  = preg_replace_callback($pattern, fn($m) => $m[1] . $m[2] . $formatted . $m[4], $content);
        }

        // Шаг 3: если ручной режим — добавляем блок цветов в конец файла.
        // Шаг 4: если задан режим темы — добавляем блок после ручного (всегда последним).
        if ($manual_mode) {
            $defaults = [
                'color-primary-light' => '#3498db', 'color-accent-light'  => '#e74c3c',
                'color-text-light'    => '#333333', 'color-bg-light'      => '#ffffff',
                'color-section-light' => '#f5f5f5', 'color-H1-light'      => '#2c3e50',
                'color-border-light'  => '#dddddd', 'color-primary-dark'  => '#2980b9',
                'color-accent-dark'   => '#c0392b', 'color-text-dark'     => '#ffffff',
                'color-bg-dark'       => '#1a1a1a', 'color-section-dark'  => '#2a2a2a',
                'color-H1-dark'       => '#5dade2', 'color-border-dark'   => '#444444',
            ];

            $block = "\n\n" . self::MANUAL_BLOCK_START . "\n";
            foreach ($this->color_vars as $var) {
                $val    = $fields[$var] ?? ($defaults[$var] ?? '#000000');
                $block .= "\${$var}: {$val};\n";
            }
            $block   .= self::MANUAL_BLOCK_END . "\n";
            $content .= $block;
        }

        if ($theme_mode !== 'both') {
            $content .= $this->build_theme_mode_block($theme_mode);
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

    public function handle_randomize(): void {
        if (!isset($_POST['u_randomize_scss']) || !check_admin_referer('u_theme_update')) return;
        if (!file_exists($this->scss_file)) return;

        $content     = file_get_contents($this->scss_file);
        $string_vars = [
            'main-menu', 'footer-menu', 'toc-menu', 'details', 'article-card',
            'font-vibe', 'style', 'is-not-section', 'is-img_contain', 'is-left-align', 'is-border',
            'font-size', 'stt-icon', 'menu-accent-align',
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

    // Удаляем блок между маркерами включительно.
    // Флаг s (DOTALL): точка захватывает переносы строк внутри блока.
    // \n* вокруг: убираем пустые строки которые остаются после удаления.
    private function remove_manual_color_block(string $content): string {
        $start   = preg_quote(self::MANUAL_BLOCK_START, '/');
        $end     = preg_quote(self::MANUAL_BLOCK_END,   '/');
        $pattern = '/\n*' . $start . '.*?' . $end . '\n*/s';
        return preg_replace($pattern, "\n", $content);
    }

    private function remove_theme_mode_block(string $content): string {
        $prefix  = preg_quote(self::THEME_MODE_BLOCK_PREFIX, '/');
        $end     = preg_quote(self::THEME_MODE_BLOCK_END, '/');
        $pattern = '/\n*' . $prefix . '.*?' . $end . '\n*/s';
        return preg_replace($pattern, "\n", $content);
    }

    private function build_theme_mode_block(string $mode): string {
        $color_keys = ['primary', 'accent', 'text', 'bg', 'section', 'H1', 'border'];
        $block = "\n\n" . self::THEME_MODE_BLOCK_PREFIX . " {$mode} */\n";
        if ($mode === 'dark-only') {
            foreach ($color_keys as $key) {
                $block .= "\$color-{$key}-light: \$color-{$key}-dark;\n";
            }
        } else { // light-only
            foreach ($color_keys as $key) {
                $block .= "\$color-{$key}-dark: \$color-{$key}-light;\n";
            }
        }
        $block .= self::THEME_MODE_BLOCK_END . "\n";
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
