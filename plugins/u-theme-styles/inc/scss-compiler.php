<?php
if (!defined('ABSPATH')) exit;

// Самодостаточный модуль: компилирует style.scss -> style.css через bundled
// standalone dart-sass (никакого Node/Docker/Composer в рантайме не требуется).
// Если папку bin/dart-sass/ удалить (откат на sass-контейнер), is_available()
// вернёт false и все публичные методы станут no-op — больше в плагине
// ничего трогать не нужно.
class UThemeScssCompiler {

    private const OPT_WATCH      = 'u_theme_scss_watch';
    private const OPT_LAST_MTIME = 'u_theme_scss_last_mtime';
    private const OPT_LOG        = 'u_theme_scss_log';
    private const CHECK_LOCK     = 'u_theme_scss_check_lock';
    private const LOG_LIMIT      = 100;

    private static function binary_path(): string {
        return __DIR__ . '/../bin/dart-sass/sass';
    }

    public static function is_available(): bool {
        $bin = self::binary_path();
        return function_exists('proc_open') && file_exists($bin) && is_executable($bin);
    }

    private static function src_dir(): string {
        return get_template_directory() . '/src';
    }

    // Принудительная пересборка — вызывается из handle_save()/handle_randomize()
    // сразу после записи conf.scss, независимо от режима watch. $source — метка
    // триггера для вкладки Logs ('save' | 'random' | 'watch' | 'deploy' и т.п.).
    public static function compile_now(string $source = 'deploy'): bool {
        if (!self::is_available()) return false;

        $start  = microtime(true);
        $dir    = self::src_dir();
        $entry  = $dir . '/style.scss';
        $target = $dir . '/style.css';
        if (!file_exists($entry)) {
            self::log_entry($source, false, microtime(true) - $start, 'style.scss не найден');
            return false;
        }

        $cmd = [
            self::binary_path(),
            '--style=compressed',
            '--no-source-map',
            '--silence-deprecation=if-function',
            $entry,
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            self::log_entry($source, false, microtime(true) - $start, 'Не удалось запустить dart-sass (proc_open)');
            return false;
        }

        $css    = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $errors = trim($errors);
            error_log('[U Theme Styles] dart-sass compile error: ' . $errors);
            self::log_entry($source, false, microtime(true) - $start, $errors);
            return false;
        }

        // style.css мог остаться от другого процесса/владельца (например,
        // от Node sass-контейнера, который пишет от root) — если открыть
        // на запись не получается, сносим и пересоздаём с нуля.
        if (file_exists($target) && !is_writable($target)) {
            @unlink($target);
        }

        if (@file_put_contents($target, $css, LOCK_EX) === false) {
            error_log('[U Theme Styles] Failed to write style.css (permission issue?)');
            self::log_entry($source, false, microtime(true) - $start, 'Не удалось записать style.css (права доступа?)');
            return false;
        }

        update_option(self::OPT_LAST_MTIME, self::max_source_mtime($dir));
        self::log_entry($source, true, microtime(true) - $start);
        return true;
    }

    // Пассивная пересборка при live-редактировании .scss-партиалов вручную.
    // Хук на 'init': дешёвый троттлинг (1 запрос/сек) + дешёвая проверка mtime,
    // дорогой компайл — только если реально что-то изменилось.
    public static function maybe_watch_compile(): void {
        if (!self::is_available()) return;
        if (!is_user_logged_in() || !current_user_can('manage_options')) return;
        if (get_option(self::OPT_WATCH, 'off') !== 'on') return;

        if (get_transient(self::CHECK_LOCK)) return;
        set_transient(self::CHECK_LOCK, 1, 1);

        $dir       = self::src_dir();
        $current   = self::max_source_mtime($dir);
        $lastBuilt = (float) get_option(self::OPT_LAST_MTIME, 0);

        if ($current > $lastBuilt) {
            self::compile_now('watch');
        }
    }

    // Читает чекбокс "Watch" из формы настроек и сохраняет опцию.
    // Вызывается из handle_save()/handle_randomize() — чекбокс живёт в той же форме.
    public static function save_watch_flag(): void {
        update_option(self::OPT_WATCH, isset($_POST['u_watch_scss']) ? 'on' : 'off');
    }

    public static function is_watch_enabled(): bool {
        return get_option(self::OPT_WATCH, 'off') === 'on';
    }

    // Кольцевой лог последних LOG_LIMIT попыток компиляции — хранится в опции
    // (не в файле: не зависит от прав на запись/владельца, работает одинаково
    // на Docker и на обычном хостинге). autoload=false — не грузится на каждый
    // чих, только когда реально открывают вкладку Logs.
    private static function log_entry(string $source, bool $ok, float $duration, string $error = ''): void {
        $log = get_option(self::OPT_LOG, []);
        if (!is_array($log)) $log = [];

        $log[] = [
            'time'     => time(),
            'source'   => $source,
            'ok'       => $ok,
            'duration' => round($duration, 3),
            'error'    => $error,
        ];

        if (count($log) > self::LOG_LIMIT) {
            $log = array_slice($log, -self::LOG_LIMIT);
        }

        update_option(self::OPT_LOG, $log, false);
    }

    // Новые записи первыми — для отображения во вкладке Logs.
    public static function get_log(): array {
        $log = get_option(self::OPT_LOG, []);
        return is_array($log) ? array_reverse($log) : [];
    }

    private static function max_source_mtime(string $dir): float {
        if (!is_dir($dir)) return 0.0;
        $max = 0.0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'scss') {
                $max = max($max, $file->getMTime());
            }
        }
        return $max;
    }
}
