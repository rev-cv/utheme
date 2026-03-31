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
        'color-success', 'color-warning', 'color-error', 'color-info',
    ];

    // Маркеры ручного блока в конце файла.
    // is_manual_mode() смотрит только на их наличие.
    private const MANUAL_BLOCK_START = '/* Manual Color Configuration */';
    private const MANUAL_BLOCK_END   = '/* End Manual Color Configuration */';

    public function __construct() {
        $this->scss_file = get_template_directory() . '/src/conf.scss';
        add_action('admin_menu',             [$this, 'add_menu']);
        add_action('admin_enqueue_scripts',  [$this, 'enqueue_assets']);
        add_action('admin_init',             [$this, 'handle_save']);
    }

    public function add_menu(): void {
        add_theme_page('U Theme Styles', 'U Theme Styles', 'manage_options', 'u-theme-styles', [$this, 'render_page']);
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'appearance_page_u-theme-styles') return;
        wp_enqueue_style('u-theme-admin-css', plugins_url('assets/admin-style.css', __FILE__));
        wp_enqueue_script('u-theme-admin-js', plugins_url('assets/admin-script.js', __FILE__), ['jquery'], null, true);
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

        // Шаг 1: всегда удаляем ручной блок из конца файла.
        // Если режим авто — на этом всё, блок исчезает и map.get снова в силе.
        // Если режим ручной — блок будет добавлен заново ниже с актуальными значениями.
        $content = $this->remove_manual_color_block($content);

        // Шаг 2: обновляем НЕ-цветовые переменные в основном теле файла.
        // Цветовые переменные ($color_vars) — не трогаем в основном теле никогда,
        // там map.get-выражения которые должны оставаться нетронутыми.
        $string_vars = [
            'main-menu', 'footer-menu', 'toc-menu', 'details', 'article-card',
            'font-vibe', 'radius-vibe', 'style', 'toc-icon',
            'is-menu-title', 'is-not-section', 'is-img_contain', 'is-left-align', 'is-border',
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

        // Шаг 3: если ручной режим — добавляем блок цветов в конец файла
        if ($manual_mode) {
            $defaults = [
                'color-primary-light' => '#3498db', 'color-accent-light'  => '#e74c3c',
                'color-text-light'    => '#333333', 'color-bg-light'      => '#ffffff',
                'color-section-light' => '#f5f5f5', 'color-H1-light'      => '#2c3e50',
                'color-border-light'  => '#dddddd', 'color-primary-dark'  => '#2980b9',
                'color-accent-dark'   => '#c0392b', 'color-text-dark'     => '#ffffff',
                'color-bg-dark'       => '#1a1a1a', 'color-section-dark'  => '#2a2a2a',
                'color-H1-dark'       => '#5dade2', 'color-border-dark'   => '#444444',
                'color-success'       => '#5db97a', 'color-warning'       => '#fcd34d',
                'color-error'         => '#dc2f02', 'color-info'          => '#4fc3f7',
            ];

            $block = "\n\n" . self::MANUAL_BLOCK_START . "\n";
            foreach ($this->color_vars as $var) {
                $val    = $fields[$var] ?? ($defaults[$var] ?? '#000000');
                $block .= "\${$var}: {$val};\n";
            }
            $block   .= self::MANUAL_BLOCK_END . "\n";
            $content .= $block;
        }

        file_put_contents($this->scss_file, $content);
        add_settings_error('u_theme', 'saved', 'Настройки сохранены. Docker запустил пересборку!', 'updated');
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

    public function render_page(): void {
        $v           = $this->get_current_values();
        $manual_mode = $this->is_manual_mode();
        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }
}

new UThemeConfigurator();
