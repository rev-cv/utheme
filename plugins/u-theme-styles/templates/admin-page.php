<?php
/**
 * Вспомогательная функция: нативный цветовой пикер.
 */
function u_color_field(string $name, string $value): void {
    // Защищаемся от map.get-выражений и SCSS-ссылок ($var) — показываем дефолт
    if (str_contains($value, '(') || str_contains($value, 'map.') || str_starts_with($value, '$')) {
        $value = '#cccccc';
    }
    $safe_value = esc_attr($value);
    $safe_name  = esc_attr($name);
    ?>
    <div class="u-color-field">
        <span class="u-color-swatch" style="background-color:<?= $safe_value ?>"></span>
        <input type="color" class="u-color-native" value="<?= $safe_value ?>" tabindex="-1" aria-hidden="true">
        <input type="text"  class="u-color-hex"    value="<?= $safe_value ?>" maxlength="7" placeholder="#000000">
        <input type="hidden" class="u-color-picker" name="<?= $safe_name ?>" value="<?= $safe_value ?>">
    </div>
    <?php
}

/**
 * Вспомогательная функция: пикер для H1 — поддерживает solid-цвет и CSS-градиент.
 * Сохраняет либо "#hex", либо "linear-gradient(Ndeg, #hex1, #hex2)".
 */
function u_h1_gradient_field(string $name, string $value): void {
    $c1 = '#cccccc'; $c2 = '#ffffff'; $angle = 135; $is_grad = false;
    if (preg_match('/^linear-gradient\(\s*(\d+)deg\s*,\s*(#[0-9a-fA-F]{3,6})\s*,\s*(#[0-9a-fA-F]{3,6})\s*\)$/', $value, $m)) {
        $is_grad = true;
        $angle   = (int) $m[1];
        $c1      = $m[2];
        $c2      = $m[3];
    } elseif (preg_match('/^#[0-9a-fA-F]{3,6}$/', $value)) {
        $c1 = $value;
    }
    $mode          = $is_grad ? 'gradient' : 'solid';
    $strip_bg      = "linear-gradient({$angle}deg, {$c1}, {$c2})";
    $safe_name     = esc_attr($name);
    $safe_val      = esc_attr($value ?: $c1);
    ?>
    <div class="u-gradient-field" data-mode="<?= $mode ?>">

        <div class="u-gradient-toggle">
            <label class="u-gradient-toggle-btn <?= !$is_grad ? 'is-active' : '' ?>">
                <input type="radio" class="u-gradient-mode-r" value="solid"    <?= !$is_grad ? 'checked' : '' ?>> Solid
            </label>
            <label class="u-gradient-toggle-btn <?= $is_grad ? 'is-active' : '' ?>">
                <input type="radio" class="u-gradient-mode-r" value="gradient" <?= $is_grad  ? 'checked' : '' ?>> Gradient
            </label>
        </div>

        <div class="u-color-field">
            <span class="u-color-swatch u-grad-c1-swatch" style="background-color:<?= esc_attr($c1) ?>"></span>
            <input type="color" class="u-color-native u-grad-c1-native" value="<?= esc_attr($c1) ?>" tabindex="-1" aria-hidden="true">
            <input type="text"  class="u-color-hex u-grad-c1-hex"       value="<?= esc_attr($c1) ?>" maxlength="7" placeholder="#000000">
        </div>

        <div class="u-gradient-extra <?= !$is_grad ? 'is-hidden' : '' ?>">
            <div class="u-color-field">
                <span class="u-color-swatch u-grad-c2-swatch" style="background-color:<?= esc_attr($c2) ?>"></span>
                <input type="color" class="u-color-native u-grad-c2-native" value="<?= esc_attr($c2) ?>" tabindex="-1" aria-hidden="true">
                <input type="text"  class="u-color-hex u-grad-c2-hex"       value="<?= esc_attr($c2) ?>" maxlength="7" placeholder="#ffffff">
            </div>
            <div class="u-gradient-angle-row">
                <span class="u-gradient-angle-label">Angle</span>
                <input type="range" class="u-gradient-angle-r" min="0" max="360" value="<?= $angle ?>">
                <output class="u-gradient-angle-out"><?= $angle ?>°</output>
            </div>
            <div class="u-gradient-strip" style="background:<?= esc_attr($strip_bg) ?>"></div>
        </div>

        <input type="hidden" name="<?= $safe_name ?>" class="u-gradient-value" value="<?= $safe_val ?>">
    </div>
    <?php
}
?>

<div class="wrap u-config-wrapper">
    <h1>U Theme Styles</h1>
    <form method="post" id="u-theme-form">
        <?php wp_nonce_field('u_theme_update'); ?>

        <nav class="tabs-nav">
            <button class="tab-btn active" data-target="basic">Basic</button>
            <button class="tab-btn" data-target="components">Components</button>
            <button class="tab-btn" data-target="colors">Colors</button>
            <button class="tab-btn" data-target="site-info">Site Info</button>
            <div class="tabs-nav-spacer"></div>
            <button type="submit" name="u_randomize_scss" value="1" class="button tabs-random-btn">&#x2684; Random</button>
            <button type="submit" name="u_save_scss" value="1" class="button button-primary tabs-save-btn">Save Settings</button>
        </nav>

        <div class="tabs-content">

            <!-- ── Basic ──────────────────────────────────────────────────── -->
            <section id="basic" class="tab-pane active">
                <?php
                $font_registry     = function_exists('u_font_registry') ? u_font_registry() : [];
                $font_vibes        = array_map(fn($v) => ['label' => $v['label'], 'desc' => $v['desc']], $font_registry);
                $font_vibe_families = array_map(fn($v) => ['hd' => $v['hd'], 'txt' => $v['txt'], 'gf' => $v['gf']], $font_registry);
                $font_sizes        = range(14, 22);
                $current_font_vibe = $v['font-vibe'] ?? 'neo-swiss';
                ?>
                <?php
                $radius_vibes = [
                    'sharp'    => ['label' => 'Sharp',    'desc' => 'Строгий, технический стиль. Минимальное скругление — ощущение точности и дисциплины.'],
                    'neutral'  => ['label' => 'Neutral',  'desc' => 'Мягкий, современный стиль. Умеренное скругление — универсальное решение для большинства проектов.'],
                    'dynamic'  => ['label' => 'Dynamic',  'desc' => 'Спортивный, адаптивный. Скругление меняется с шириной экрана, кнопки всегда в виде пилюли.'],
                    'rounded'  => ['label' => 'Rounded',  'desc' => 'Максимально мягкие, дружелюбные формы. Идеально для детских, lifestyle и wellness-проектов.'],
                    'velocity' => ['label' => 'Velocity', 'desc' => 'Скорость и напор. Диагональная асимметрия — острый угол спереди, скругление сзади.'],
                    'chess'   => ['label' => 'Chess',   'desc' => 'Шахматный паттерн. Скруглены два противоположных угла по другой диагонали — карточки и кнопки выглядят как вырезанные из бумаги.'],
                    'sticker' => ['label' => 'Sticker', 'desc' => 'Бейдж и стикер. Крупные фиксированные радиусы, шапка скруглена только снизу — сайт выглядит как набор карточек-наклеек.'],
                ];
                $current_radius_vibe = $v['radius-vibe'] ?? 'neutral';
                $radius_vibe_css = [
                    'sharp'    => '2px',
                    'neutral'  => '8px',
                    'dynamic'  => '16px',
                    'rounded'  => '20px',
                    'velocity' => '8px 32px 8px 32px',
                    'chess'    => '0px 20px 0px 20px',
                    'sticker'  => '28px',
                ];
                ?>

                <!-- ── Card 1: Font Size ──────────────────────────────────── -->
                <div class="u-card">
                    <div class="u-card-header">
                        <h3>Font Size</h3>
                    </div>
                    <div class="u-basic-field">
                        <div class="u-basic-field-control">
                            <select name="u_fields[font-size]">
                                <?php foreach ($font_sizes as $size): ?>
                                    <option value="<?= $size ?>px"
                                        <?= ($v['font-size'] ?? '16px') === $size . 'px' ? 'selected' : '' ?>>
                                        <?= $size ?>px
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="u-desc">Базовый размер шрифта сайта. Все остальные размеры масштабируются относительно него.</p>
                    </div>
                </div>

                <!-- ── Card 2: Font Vibe + Typography ────────────────────── -->
                <div class="u-card">
                    <div class="u-card-header">
                        <h3>Font &amp; Typography</h3>
                    </div>
                    <div class="u-card-body">
                        <div class="u-card-left">

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Font Vibe</div>
                                <div class="u-basic-field-control">
                                    <select name="u_fields[font-vibe]" id="font-vibe-select">
                                        <?php foreach ($font_vibes as $val => $fv): ?>
                                            <option value="<?= $val ?>"
                                                <?= $current_font_vibe === $val ? 'selected' : '' ?>>
                                                <?= $fv['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="u-desc" id="font-vibe-desc"><?= $font_vibes[$current_font_vibe]['desc'] ?? '' ?></p>
                                <script>
                                var uFontVibeDescs = <?php
                                    $descs = [];
                                    foreach ($font_vibes as $k => $fv) $descs[$k] = $fv['desc'];
                                    echo json_encode($descs, JSON_UNESCAPED_UNICODE);
                                ?>;
                                var uFontVibeFamilies = <?= json_encode($font_vibe_families, JSON_UNESCAPED_UNICODE) ?>;
                                </script>
                            </div>

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Typography</div>
                                <?php
                                $hd_weight    = $v['hd-weight']          ?? '900';
                                $hd_height    = $v['hd-height']          ?? '1em';
                                $hd_ls        = $v['hd-letter-spacing']  ?? '-0.02em';
                                $hd_case      = $v['hd-case']            ?? 'uppercase';
                                $hd_italic    = $v['hd-italic']          ?? 'normal';
                                $txt_weight   = $v['txt-weight']         ?? '400';
                                $txt_height   = $v['txt-height']         ?? '1.55em';
                                $txt_ls       = $v['txt-letter-spacing'] ?? '0em';
                                $hd_height_n  = rtrim($hd_height, 'em') ?: '1';
                                $hd_ls_n      = rtrim($hd_ls,     'em') ?: '-0.02';
                                $txt_height_n = rtrim($txt_height, 'em') ?: '1.55';
                                $txt_ls_n     = rtrim($txt_ls,     'em') ?: '0';
                                ?>
                                <div class="u-typo-presets">
                                    <span class="u-typo-presets-label">Presets:</span>
                                    <button type="button" class="u-typo-preset button" data-preset="soft">Soft</button>
                                    <button type="button" class="u-typo-preset button" data-preset="impact">Impact</button>
                                    <button type="button" class="u-typo-preset button" data-preset="monolith">Monolith</button>
                                    <button type="button" class="u-typo-preset button" data-preset="open">Open</button>
                                </div>

                                <div class="u-typo-table">

                                    <span></span>
                                    <span class="u-typo-col-head">Heading</span>
                                    <span class="u-typo-col-head">Text</span>

                                    <span class="u-typo-label">Weight</span>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[hd-weight]"
                                               min="100" max="900" step="100"
                                               value="<?= esc_attr($hd_weight) ?>"
                                               oninput="document.getElementById('hd-weight-output').textContent = this.value">
                                        <output id="hd-weight-output"><?= esc_html($hd_weight) ?></output>
                                    </div>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[txt-weight]"
                                               min="100" max="900" step="100"
                                               value="<?= esc_attr($txt_weight) ?>"
                                               oninput="document.getElementById('txt-weight-output').textContent = this.value">
                                        <output id="txt-weight-output"><?= esc_html($txt_weight) ?></output>
                                    </div>

                                    <span class="u-typo-label">Line Height</span>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[hd-height]"
                                               min="0.8" max="1.6" step="0.05"
                                               value="<?= esc_attr($hd_height_n) ?>"
                                               oninput="document.getElementById('hd-height-output').textContent = fmtEm(this.value)">
                                        <output id="hd-height-output"><?= esc_html($hd_height) ?></output>
                                    </div>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[txt-height]"
                                               min="1.2" max="2.2" step="0.05"
                                               value="<?= esc_attr($txt_height_n) ?>"
                                               oninput="document.getElementById('txt-height-output').textContent = fmtEm(this.value)">
                                        <output id="txt-height-output"><?= esc_html($txt_height) ?></output>
                                    </div>

                                    <span class="u-typo-label">Spacing</span>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[hd-letter-spacing]"
                                               min="-0.08" max="0.08" step="0.005"
                                               value="<?= esc_attr($hd_ls_n) ?>"
                                               oninput="document.getElementById('hd-ls-output').textContent = fmtEm(this.value)">
                                        <output id="hd-ls-output"><?= esc_html($hd_ls) ?></output>
                                    </div>
                                    <div class="u-typo-ctrl">
                                        <input type="range" name="u_fields[txt-letter-spacing]"
                                               min="-0.04" max="0.04" step="0.005"
                                               value="<?= esc_attr($txt_ls_n) ?>"
                                               oninput="document.getElementById('txt-ls-output').textContent = fmtEm(this.value)">
                                        <output id="txt-ls-output"><?= esc_html($txt_ls) ?></output>
                                    </div>

                                    <span class="u-typo-label">Case</span>
                                    <select name="u_fields[hd-case]" class="u-typo-case-select">
                                        <option value="none"      <?= $hd_case === 'none'      ? 'selected' : '' ?>>Normal</option>
                                        <option value="uppercase" <?= $hd_case === 'uppercase' ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="lowercase" <?= $hd_case === 'lowercase' ? 'selected' : '' ?>>Lowercase</option>
                                    </select>
                                    <span></span>

                                    <span class="u-typo-label">Style</span>
                                    <select name="u_fields[hd-italic]" class="u-typo-case-select">
                                        <option value="normal" <?= $hd_italic === 'normal' ? 'selected' : '' ?>>Normal</option>
                                        <option value="italic" <?= $hd_italic === 'italic' ? 'selected' : '' ?>>Italic</option>
                                    </select>
                                    <span></span>

                                </div>
                                <p class="u-desc">Типографика заголовков и основного текста. Пресеты задают базовые комбинации, значения можно скорректировать вручную.</p>
                            </div>

                        </div>

                        <div class="u-card-right">
                            <?php
                            $fvf = $font_vibe_families[$current_font_vibe] ?? $font_vibe_families['neo-swiss'];
                            if (!empty($fvf['gf'])): ?>
                            <link rel="stylesheet" href="<?= esc_url($fvf['gf']) ?>">
                            <?php endif; ?>
                            <div class="u-preview u-preview--tall">
                                <div class="u-font-preview-box"
                                     id="font-vibe-preview"
                                     style="
                                        --fpb-hd: <?= esc_attr($fvf['hd']) ?>;
                                        --fpb-txt: <?= esc_attr($fvf['txt']) ?>;
                                        --fpb-hd-w: <?= esc_attr($hd_weight) ?>;
                                        --fpb-hd-lh: <?= esc_attr($hd_height) ?>;
                                        --fpb-hd-ls: <?= esc_attr($hd_ls) ?>;
                                        --fpb-hd-case: <?= esc_attr($hd_case) ?>;
                                        --fpb-hd-style: <?= esc_attr($hd_italic) ?>;
                                        --fpb-txt-w: <?= esc_attr($txt_weight) ?>;
                                        --fpb-txt-lh: <?= esc_attr($txt_height) ?>;
                                        --fpb-txt-ls: <?= esc_attr($txt_ls) ?>">
                                    <div class="u-font-preview-hd">Heading sample</div>
                                    <div class="u-font-preview-txt">The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Card 3: General ────────────────────────────────────── -->
                <div class="u-card">
                    <div class="u-card-header">
                        <h3>General</h3>
                    </div>
                    <div class="u-card-body">
                        <div class="u-card-left">

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Radius Vibe</div>
                                <div class="u-basic-field-control">
                                    <select name="u_fields[radius-vibe]" id="radius-vibe-select">
                                        <?php foreach ($radius_vibes as $val => $rv): ?>
                                            <option value="<?= $val ?>"
                                                <?= $current_radius_vibe === $val ? 'selected' : '' ?>>
                                                <?= $rv['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="u-desc" id="radius-vibe-desc"><?= $radius_vibes[$current_radius_vibe]['desc'] ?? '' ?></p>
                                <script>
                                var uRadiusVibeDescs = <?php
                                    $rdescs = [];
                                    foreach ($radius_vibes as $k => $rv) $rdescs[$k] = $rv['desc'];
                                    echo json_encode($rdescs, JSON_UNESCAPED_UNICODE);
                                ?>;
                                var uRadiusVibeCss = <?= json_encode($radius_vibe_css) ?>;
                                </script>
                            </div>

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">
                                    Density Factor
                                    <output id="density-output"><?= $v['density-factor'] ?? '1' ?></output>
                                </div>
                                <div class="u-basic-field-control">
                                    <input type="range" name="u_fields[density-factor]"
                                           min="0.75" max="1.5" step="0.05"
                                           value="<?= $v['density-factor'] ?? '1' ?>"
                                           oninput="document.getElementById('density-output').textContent = this.value">
                                </div>
                                <p class="u-desc">Множитель пространства между элементами и строками. Меньше — компактнее, больше — воздушнее.</p>
                            </div>

                            <?php
                            $max_width_raw = $v['max-width'] ?? '1200px';
                            $max_width_n   = (int) rtrim($max_width_raw, 'px');
                            $width_presets = [960, 1024, 1140, 1200, 1280, 1440];
                            ?>
                            <div class="u-basic-field">
                                <div class="u-basic-field-label">
                                    Content Width
                                    <output id="max-width-output"><?= esc_html($max_width_raw) ?></output>
                                </div>
                                <div class="u-basic-field-control">
                                    <input type="range" name="u_fields[max-width]" id="max-width-slider"
                                           min="800" max="1920" step="10"
                                           value="<?= esc_attr($max_width_n) ?>"
                                           oninput="document.getElementById('max-width-output').textContent = this.value + 'px'; uSyncWidthPresets(this.value)">
                                </div>
                                <div class="u-width-presets">
                                    <span class="u-width-presets-label">Presets:</span>
                                    <?php foreach ($width_presets as $preset): ?>
                                        <button type="button" class="u-width-preset button <?= $max_width_n === $preset ? 'is-active' : '' ?>"
                                                data-width="<?= $preset ?>">
                                            <?= $preset ?>px
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <p class="u-desc">Максимальная ширина области контента. Определяет, насколько широко растягивается основной блок на больших экранах.</p>
                            </div>
                            <script>
                            function uSyncWidthPresets(val) {
                                var w = parseInt(val, 10);
                                document.querySelectorAll('.u-width-preset').forEach(function(b) {
                                    b.classList.toggle('is-active', parseInt(b.dataset.width, 10) === w);
                                });
                            }
                            document.querySelectorAll('.u-width-preset').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var w = this.dataset.width;
                                    var slider = document.getElementById('max-width-slider');
                                    slider.value = w;
                                    document.getElementById('max-width-output').textContent = w + 'px';
                                    uSyncWidthPresets(w);
                                });
                            });
                            </script>

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Flags</div>
                                <div class="u-basic-field-control u-basic-checkboxes">
                                    <label class="u-checkbox-label">
                                        <input type="checkbox" name="u_fields[is-border]" value="true"
                                            <?= ($v['is-border'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                        <span>
                                            <strong>Show Borders</strong>
                                            <span class="u-desc">Компоненты, поддерживающие этот режим, будут отображать рамку.</span>
                                        </span>
                                    </label>
                                    <label class="u-checkbox-label">
                                        <input type="checkbox" name="u_fields[is-left-align]" value="true"
                                            <?= ($v['is-left-align'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                        <span>
                                            <strong>Left Align</strong>
                                            <span class="u-desc">Все элементы выравниваются по левому краю. По умолчанию — стандартное центрированное выравнивание.</span>
                                        </span>
                                    </label>

                                </div>
                            </div>

                        </div>

                        <div class="u-card-right">
                            <div class="u-preview u-preview--tall">
                                <div class="u-radius-preview-box"
                                     id="radius-vibe-preview"
                                     style="border-radius: <?= esc_attr($radius_vibe_css[$current_radius_vibe] ?? '8px') ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Components ─────────────────────────────────────────────── -->
            <section id="components" class="tab-pane">
                <h2>Components</h2>
                <?php
                // Читаем иконки из scheme.icons.scss.
                // Извлекаем SVG-код из url('data:image/svg+xml;utf8,...') для inline-рендера.
                $toc_icons  = []; // ['name' => '<svg>...</svg>']
                $icons_file = get_template_directory() . '/src/scheme.icons.scss';

                if (file_exists($icons_file)) {
                    $icons_raw = file_get_contents($icons_file);
                    // Матчим: 'name': url('...') или 'name': url("...") — захватываем до закрывающих кавычек + )
                    preg_match_all("/'([\w-]+)'\s*:\s*url\('(.+?)'\)/", $icons_raw, $matches_single);
                    preg_match_all('/\'([\w-]+)\'\s*:\s*url\("(.+?)"\)/', $icons_raw, $matches_double);
                    
                    // Одинарные кавычки
                    foreach ($matches_single[1] as $i => $icon_name) {
                        $svg_data = trim($matches_single[2][$i]);
                        // Удаляем префикс data:image/svg+xml;utf8, или data:image/svg+xml, и декодируем
                        $svg_data = preg_replace('/^data:image\/svg\+xml(;utf8)?,/', '', $svg_data);
                        $svg = rawurldecode($svg_data);
                        $toc_icons[$icon_name] = $svg;
                    }
                    // Двойные кавычки
                    foreach ($matches_double[1] as $i => $icon_name) {
                        $svg_data = trim($matches_double[2][$i]);
                        // Удаляем префикс data:image/svg+xml;utf8, или data:image/svg+xml, и декодируем
                        $svg_data = preg_replace('/^data:image\/svg\+xml(;utf8)?,/', '', $svg_data);
                        $svg = rawurldecode($svg_data);
                        $toc_icons[$icon_name] = $svg;
                    }
                }

                $stt_icons  = [];
                $arrows_file = get_template_directory() . '/src/scheme.icons.arrows.scss';
                if (file_exists($arrows_file)) {
                    $arrows_raw = file_get_contents($arrows_file);
                    preg_match_all("/'([\w-]+)'\s*:\s*url\('(.+?)'\)/", $arrows_raw, $arr_single);
                    preg_match_all('/\'([\w-]+)\'\s*:\s*url\("(.+?)"\)/', $arrows_raw, $arr_double);
                    foreach ($arr_single[1] as $i => $icon_name) {
                        $svg_data = preg_replace('/^data:image\/svg\+xml(;utf8)?,/', '', trim($arr_single[2][$i]));
                        $stt_icons[$icon_name] = rawurldecode($svg_data);
                    }
                    foreach ($arr_double[1] as $i => $icon_name) {
                        $svg_data = preg_replace('/^data:image\/svg\+xml(;utf8)?,/', '', trim($arr_double[2][$i]));
                        $stt_icons[$icon_name] = rawurldecode($svg_data);
                    }
                }

                $components = [
                    'main-menu' => [
                        'title'       => 'Main Menu',
                        'desc'        => 'Стиль отображения главного меню сайта.',
                        'options'     => ['island', 'aside', 'boring', 'docs', 'newspaper', 'hierarchical'],
                        'switch'      => 'is-menu-title',
                        'switch_desc' => 'Показывать название сайта в главном меню',
                    ],
                    'footer-menu' => [
                        'title'   => 'Footer Menu',
                        'desc'    => 'Стиль отображения меню в подвале сайта.<br><b>2columns</b> — два столбца со ссылками.<br><b>4columns</b> — четыре колонки: брендинг, ответственная игра, юридическое, страницы.',
                        'options' => ['2columns', '4columns'],
                    ],
                    'toc-menu' => [
                        'title'       => 'Table of Contents',
                        'desc'        => '',
                        'options'     => ['circle', 'number', 'icon', 'tags', 'vertical-rule', 'two-columns', 'underline', 'card-row', 'numbers-right'],
                        'switch'      => 'is-not-section',
                        'switch_desc' => 'Отобразить оглавление без секции',
                        'icon_select' => true,
                    ],
                    'article-card' => [
                        'title'   => 'Article Card',
                        'desc'    => 'Внешний вид карточки статьи в списках и архивах.<br>
                            <b>Default</b> — базовая карточка с изображением и ссылкой.<br>
                            <b>Frame</b> — карточка в рамке.<br>
                            <b>Slide</b> — карточка-слайд.<br>
                            <b>Windows</b> — плиточный стиль.<br>
                            <b>Float</b> — заголовок поверх изображения.<br>
                            <b>Soft</b> — мягкий стиль без жёстких границ.<br>
                            <b>Split</b> — диагональное разделение с анимацией.<br>
                            <b>Classic</b> — изображение 16:10 сверху, чистый контент снизу.<br>
                            <b>Aside</b> — горизонтальный сплит: картинка 42% слева, текст справа.<br>
                            <b>Overlay</b> — полноразмерное фото, текст поверх с градиентом.<br>
                            <b>Blurred</b> — размытый фон-декор, чёткий thumbnail в углу.<br>
                            <b>Type-first</b> — заголовок-герой с inline-миниатюрой.<br>
                            <b>Editorial</b> — редакционный стиль, верхняя граница, thumbnail.<br>
                            <b>Clipped</b> — изображение с float, текст обтекает.',
                        'options' => ['default', 'frame', 'slide', 'windows', 'float', 'soft', 'split', 'classic', 'aside', 'overlay', 'blurred', 'type-first', 'editorial', 'clipped'],
                    ],
                    'table-style' => [
                        'title'   => 'Table Style',
                        'desc'    => 'Вариант стилизации таблиц (.wp-block-table) в статьях.<br>
                            <b>Default</b> — насыщенная шапка в цвет акцента, внешняя тень.<br>
                            <b>Minimal</b> — без рамок, только нижние линии строк, UPPERCASE-заголовки.<br>
                            <b>Classic</b> — полная сетка с рамками вокруг каждой ячейки.<br>
                            <b>Cards</b> — строки как отдельные карточки с тенью и акцентной полоской слева.<br>
                            <b>Stripes</b> — тёмная контрастная шапка + зебра чётных строк.<br>
                            <b>Bold</b> — шапка-блок в цвет акцента с вертикальными разделителями.<br>
                            <b>Outlined</b> — одна скруглённая рамка вокруг всей таблицы.<br>
                            <b>Dashed</b> — пунктирные линии, заголовки и футер в цвет акцента.<br>
                            <b>Tinted</b> — тонированная шапка и футер, вертикальные разделители.<br>
                            <b>Editorial</b> — крупные заголовки, щедрые отступы, журнальный стиль.',
                        'options' => ['default', 'minimal', 'classic', 'cards', 'stripes', 'bold', 'outlined', 'dashed', 'tinted', 'editorial'],
                    ],
                    'more-pages' => [
                        'title'   => 'More Pages',
                        'desc'    => 'Блок "ещё почитать" под основным контентом страницы.<br><b>Grid</b> — мозаичная сетка с картинками.<br><b>List</b> — компактный список в несколько колонок.<br><b>Slider</b> — CSS-слайдер, одна статья на весь экран.<br><b>Carousel</b> — JS-карусель: 2–3 карточки одновременно, drag + автопрокрутка.',
                        'options' => ['grid', 'list', 'slider', 'carousel'],
                    ],
                ];

                foreach ($components as $id => $data): ?>
                    <div class="u-component-card">
                        <div class="u-card-header">
                            <h3><?= $data['title'] ?></h3>
                            <?php if (isset($data['options'])): ?>
                                <select name="u_fields[<?= $id ?>]" class="u-component-select">
                                    <?php foreach ($data['options'] as $opt): ?>
                                        <?php
                                            // Определяем метку для опции
                                            $label = ucfirst($opt);
                                            if ($id === 'main-menu') {
                                                if ($opt === 'aside' || $opt === 'boring') {
                                                    $label .= ' [3LEV]';
                                                } elseif ($opt === 'hierarchical') {
                                                    $label .= ' [2LEV]';
                                                }
                                            }
                                        ?>
                                        <option value="<?= $opt ?>"
                                            <?= ($v[$id] ?? $data['options'][0]) === $opt ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="u-card-body">
                            <div class="u-card-left">

                                <?php if (isset($data['switch'])): ?>
                                    <label class="u-checkbox-label">
                                        <input type="checkbox"
                                               name="u_fields[<?= $data['switch'] ?>]"
                                               value="true"
                                               <?= ($v[$data['switch']] ?? '') === 'true' ? 'checked' : '' ?>>
                                        <span><?= $data['switch_desc'] ?? $data['switch'] ?></span>
                                    </label>
                                <?php endif; ?>

                                <?php if ($id === 'toc-menu'): ?>
                                    <label class="u-checkbox-label">
                                        <input type="checkbox"
                                               name="u_fields[toc-show-title]"
                                               value="true"
                                               <?= ($v['toc-show-title'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                        <span>Показывать заголовок оглавления</span>
                                    </label>
                                <?php endif; ?>

                                <?php if ($id === 'main-menu'): ?>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span class="u-basic-field-label">Accent text align</span>
                                        <?php foreach (['left', 'center', 'right'] as $align_opt): ?>
                                            <label class="u-checkbox-label">
                                                <input type="radio"
                                                       name="u_fields[menu-accent-align]"
                                                       value="<?= $align_opt ?>"
                                                       <?= ($v['menu-accent-align'] ?? 'center') === $align_opt ? 'checked' : '' ?>>
                                                <span><?= ucfirst($align_opt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($data['icon_select']) && !empty($toc_icons)): ?>
                                    <?php
                                    $current_icon     = $v['toc-icon'] ?? array_key_first($toc_icons);
                                    $current_icon_svg = $toc_icons[$current_icon] ?? '';
                                    $is_icon_mode     = ($v['toc-menu'] ?? 'circle') === 'icon';
                                    ?>
                                    <div class="u-icon-select-wrap"
                                         <?= $is_icon_mode ? '' : 'style="display:none"' ?>
                                         data-toc-icon-wrap>
                                        <label class="u-basic-field-label">TOC Icon</label>

                                        <!-- Скрытый input — значение для формы -->
                                        <input type="hidden" name="u_fields[toc-icon]"
                                               id="toc-icon-value"
                                               value="<?= esc_attr($current_icon) ?>">

                                        <!-- Кнопка-триггер dropdown -->
                                        <div class="u-icon-dropdown" id="toc-icon-dropdown" data-input-name="toc-icon">
                                            <button type="button" class="u-icon-trigger" id="toc-icon-trigger">
                                                <span class="u-icon-svg"><?= $current_icon_svg ?></span>
                                                <span class="u-icon-trigger-name" id="toc-icon-label"><?= esc_html($current_icon) ?></span>
                                                <span class="u-icon-chevron">▾</span>
                                            </button>

                                            <!-- Список опций -->
                                            <div class="u-icon-list" id="toc-icon-list" style="display:none">
                                                <?php foreach ($toc_icons as $icon_name => $svg): ?>
                                                    <div class="u-icon-item <?= $current_icon === $icon_name ? 'is-selected' : '' ?>"
                                                         data-value="<?= esc_attr($icon_name) ?>">
                                                        <span class="u-icon-svg"><?= $svg ?></span>
                                                        <span class="u-icon-item-name"><?= esc_html($icon_name) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($id === 'toc-menu' && !empty($stt_icons)): ?>
                                    <?php
                                    $current_stt_icon     = $v['stt-icon'] ?? array_key_first($stt_icons);
                                    $current_stt_icon_svg = $stt_icons[$current_stt_icon] ?? '';
                                    ?>
                                    <div class="u-icon-select-wrap">
                                        <label class="u-basic-field-label">Scroll to Top Icon</label>
                                        <input type="hidden" name="u_fields[stt-icon]"
                                               id="stt-icon-value"
                                               value="<?= esc_attr($current_stt_icon) ?>">
                                        <div class="u-icon-dropdown" id="stt-icon-dropdown" data-input-name="stt-icon">
                                            <button type="button" class="u-icon-trigger" id="stt-icon-trigger">
                                                <span class="u-icon-svg"><?= $current_stt_icon_svg ?></span>
                                                <span class="u-icon-trigger-name" id="stt-icon-label"><?= esc_html($current_stt_icon) ?></span>
                                                <span class="u-icon-chevron">▾</span>
                                            </button>
                                            <div class="u-icon-list" id="stt-icon-list" style="display:none">
                                                <?php foreach ($stt_icons as $icon_name => $svg): ?>
                                                    <div class="u-icon-item <?= $current_stt_icon === $icon_name ? 'is-selected' : '' ?>"
                                                         data-value="<?= esc_attr($icon_name) ?>">
                                                        <span class="u-icon-svg"><?= $svg ?></span>
                                                        <span class="u-icon-item-name"><?= esc_html($icon_name) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <label class="u-checkbox-label">
                                        <input type="checkbox" name="u_fields[stt-ghost]" value="true"
                                            <?= ($v['stt-ghost'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                        <span>
                                            <strong>Ghost Mode</strong>
                                            <span class="u-desc">Прозрачная кнопка — иконка инвертирует цвета контента под ней.</span>
                                        </span>
                                    </label>
                                <?php endif; ?>

                                <p class="u-desc"><?= $data['desc'] ?? '' ?></p>
                            </div>

                            <div class="u-card-right">
                                <div class="u-preview">
                                    <img src="<?= plugins_url("assets/media/{$id}-" . ($v[$id] ?? $data['options'][0] ?? $id) . ".webp", dirname(__FILE__)) ?>"
                                         data-base-url="<?= plugins_url("assets/media/{$id}-", dirname(__FILE__)) ?>"
                                         alt="<?= esc_attr($data['title']) ?>"
                                         class="u-component-preview-img"
                                         onerror="this.closest('.u-card-right').style.display='none'">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- ── Image Style ───────────────────────────────────── -->
                <?php
                $image_style_options = ['original', 'marginalia', 'slide-up', 'whisper', 'corner-badge', 'brutalist-strip'];
                $current_image_style = $v['image-style'] ?? 'original';
                $image_style_descs = [
                    'original'       => 'Базовый вариант: картинка 16:9, подпись по центру (или слева при Left Align).',
                    'marginalia'     => 'Подпись живёт в левом поле — как сноска в книге. На мобильном уходит под картинку.',
                    'slide-up'       => 'Подпись скрыта и всплывает снизу при наведении. На touch-устройствах — по тапу.',
                    'whisper'        => 'Крошечная моно-подпись со стрелкой — почти незаметна, не ломает чтение.',
                    'corner-badge'   => 'В углу маленький [i] — при наведении плавно разворачивается в полную подпись.',
                    'brutalist-strip' => 'Жёсткая чёрная рамка вокруг картинки, подпись всегда в чёрной полосе снизу.',
                ];
                ?>
                <div class="u-component-card">
                    <div class="u-card-header">
                        <h3>Image Style</h3>
                        <select name="u_fields[image-style]" class="u-component-select" id="image-style-select">
                            <?php foreach ($image_style_options as $opt): ?>
                                <option value="<?= $opt ?>"
                                    <?= $current_image_style === $opt ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('-', ' ', $opt)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="u-card-body">
                        <div class="u-card-left">
                            <p class="u-desc" id="image-style-desc">
                                <?= $image_style_descs[$current_image_style] ?? '' ?>
                            </p>
                            <script>
                            var uImageStyleDescs = <?= json_encode($image_style_descs, JSON_UNESCAPED_UNICODE) ?>;
                            document.getElementById('image-style-select').addEventListener('change', function () {
                                var desc = uImageStyleDescs[this.value] || '';
                                document.getElementById('image-style-desc').textContent = desc;
                                var img = document.getElementById('image-style-preview');
                                if (img) img.src = img.dataset.baseUrl + this.value + '.webp';
                            });
                            </script>
                        </div>
                        <div class="u-card-right">
                            <div class="u-preview">
                                <img id="image-style-preview"
                                     src="<?= plugins_url("assets/media/image-style-{$current_image_style}.webp", dirname(__FILE__)) ?>"
                                     data-base-url="<?= plugins_url('assets/media/image-style-', dirname(__FILE__)) ?>"
                                     alt="Image style preview"
                                     class="u-component-preview-img"
                                     onerror="this.closest('.u-card-right').style.display='none'">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Callout ─────────────────────────────────────────── -->
                <?php
                $callout_options     = ['default', 'subtle-tinted', 'left-accent-bar', 'solid-filled', 'dashed-outline', 'icon-badge-card', 'minimal-inline'];
                $current_callout     = $v['callout'] ?? 'default';
                $current_icon_set    = $v['callout-icon-set'] ?? 'circle';
                $callout_icon_sets   = [];

                // Парсим scheme.icons.callouts.scss: извлекаем наборы с 4 inline-SVG
                $callout_icons_file = get_template_directory() . '/src/scheme.icons.callouts.scss';
                if (file_exists($callout_icons_file)) {
                    $raw      = file_get_contents($callout_icons_file);
                    $cur_set  = null;
                    $cur_icons = [];
                    foreach (explode("\n", $raw) as $line) {
                        // Начало набора: '    'name': ('
                        if (preg_match("/^\s+'([\w-]+)':\s*\(\s*$/", $line, $m)) {
                            $cur_set   = $m[1];
                            $cur_icons = [];
                        // Строка с иконкой (utf8 format): 'type': url('data:image/svg+xml;utf8,...'),
                        } elseif ($cur_set && preg_match(
                            "/^\s+'(info|success|warning|danger)':\s*url\('data:image\/svg\+xml;utf8,(.*?)'\)\s*,?\s*$/",
                            $line, $m
                        )) {
                            $cur_icons[$m[1]] = trim($m[2]);
                        // Строка с иконкой (percent-encoded format): 'type': url("data:image/svg+xml,..."),
                        } elseif ($cur_set && preg_match(
                            "/^\s+'(info|success|warning|danger)':\s*url\(\"data:image\/svg\+xml,(.*?)\"\)\s*,?\s*$/",
                            $line, $m
                        )) {
                            $cur_icons[$m[1]] = rawurldecode(trim($m[2]));
                        // Конец набора: '    ),'
                        } elseif ($cur_set && preg_match("/^\s+\),?\s*$/", $line) && count($cur_icons) === 4) {
                            $callout_icon_sets[$cur_set] = $cur_icons;
                            $cur_set = null;
                        }
                    }
                }

                // Иконки активного набора (fallback: первый набор)
                $active_set_icons = $callout_icon_sets[$current_icon_set]
                    ?? (reset($callout_icon_sets) ?: []);
                ?>
                <div class="u-component-card">
                    <div class="u-card-header">
                        <h3>Callout</h3>
                        <select name="u_fields[callout]" class="u-component-select" id="callout-select">
                            <?php foreach ($callout_options as $opt): ?>
                                <option value="<?= $opt ?>"
                                    <?= $current_callout === $opt ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('-', ' ', $opt)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="u-card-body">
                        <div class="u-card-left">

                            <?php if (!empty($callout_icon_sets)): ?>
                                <div class="u-icon-select-wrap">
                                    <label class="u-basic-field-label">Icon Set</label>

                                    <input type="hidden"
                                           name="u_fields[callout-icon-set]"
                                           id="callout-icon-set-value"
                                           value="<?= esc_attr($current_icon_set) ?>">

                                    <div class="u-icon-dropdown"
                                         id="callout-icon-set-dropdown"
                                         data-input-name="callout-icon-set">

                                        <button type="button" class="u-icon-trigger" id="callout-icon-set-trigger">
                                            <span class="u-icon-svg u-icon-svg--set">
                                                <?= $active_set_icons['info']    ?? '' ?>
                                                <?= $active_set_icons['success'] ?? '' ?>
                                                <?= $active_set_icons['warning'] ?? '' ?>
                                                <?= $active_set_icons['danger']  ?? '' ?>
                                            </span>
                                            <span class="u-icon-trigger-name" id="callout-icon-set-label">
                                                <?= esc_html($current_icon_set) ?>
                                            </span>
                                            <span class="u-icon-chevron">▾</span>
                                        </button>

                                        <div class="u-icon-list" id="callout-icon-set-list" style="display:none">
                                            <?php foreach ($callout_icon_sets as $set_name => $icons): ?>
                                                <div class="u-icon-item <?= $current_icon_set === $set_name ? 'is-selected' : '' ?>"
                                                     data-value="<?= esc_attr($set_name) ?>">
                                                    <span class="u-icon-svg u-icon-svg--set">
                                                        <?= $icons['info']    ?>
                                                        <?= $icons['success'] ?>
                                                        <?= $icons['warning'] ?>
                                                        <?= $icons['danger']  ?>
                                                    </span>
                                                    <span class="u-icon-item-name"><?= esc_html($set_name) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <p class="u-desc">
                                Вариант информационных блоков (callout).<br>
                                <b>Default</b> — акцентная левая рамка без иконки.<br>
                                <b>Subtle Tinted</b> — полная рамка, тонированный фон, боковая иконка слева.<br>
                                <b>Left Accent Bar</b> — 4px левая полоса, тонированный фон, иконка, острые углы.<br>
                                <b>Solid Filled</b> — насыщенный сплошной фон, белый текст, как баннер-алерт.<br>
                                <b>Dashed Outline</b> — без фона, пунктирная рамка в цвете акцента.<br>
                                <b>Icon Badge Card</b> — белая карточка с тенью, иконка.<br>
                                <b>Minimal Inline</b> — без фона и рамки, тонкая левая линия и акцентная точка.<br>
                                Icon Set применяется в вариантах Subtle Tinted, Left Accent Bar и Icon Badge Card.
                            </p>
                        </div>

                        <div class="u-card-right">
                            <div class="u-preview">
                                <img src="<?= plugins_url("assets/media/callout-{$current_callout}.webp", dirname(__FILE__)) ?>"
                                     data-base-url="<?= plugins_url('assets/media/callout-', dirname(__FILE__)) ?>"
                                     alt="Callout preview"
                                     class="u-component-preview-img"
                                     onerror="this.closest('.u-card-right').style.display='none'">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="u-component-card">
                    <div class="u-card-header">
                        <h3>Breadcrumbs</h3>
                        <select name="u_fields[breadcrumbs-separator]">
                            <?php
                            $sep_options = ['/' => '/', '|' => '|', '»' => '»', '>' => '>', '-' => '-'];
                            $current_sep = $v['breadcrumbs-separator'] ?? '/';
                            foreach ($sep_options as $val => $label): ?>
                                <option value="<?= esc_attr($val) ?>"
                                    <?= $current_sep === $val ? 'selected' : '' ?>>
                                    <?= esc_html($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="u-card-body">
                        <div class="u-card-left">
                            <p class="u-desc">Разделитель между элементами хлебных крошек.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Colors ─────────────────────────────────────────────────── -->
            <section id="colors" class="tab-pane">
                <h2>Colors</h2>

                <div class="color-mode-switcher">
                    <label class="switcher-label">
                        <span>Auto Generator</span>
                        <label class="switch">
                            <input type="checkbox" name="u_color_mode" value="manual"
                                   <?= $manual_mode ? 'checked' : '' ?>
                                   id="color-mode-toggle">
                            <span class="slider"></span>
                        </label>
                        <span>Manual Setup</span>
                    </label>
                    <span class="switcher-status <?= $manual_mode ? 'manual' : 'auto' ?>"
                          id="color-mode-status">
                        <?= $manual_mode ? 'Manual' : 'Auto' ?>
                    </span>
                </div>

                <div class="u-theme-mode-wrap">
                    <div class="u-theme-mode-group">
                        <?php
                        $mode_options = [
                            'dark-only'  => 'Dark Only',
                            'both'       => 'Dark &amp; Light',
                            'light-only' => 'Light Only',
                        ];
                        foreach ($mode_options as $val => $lbl): ?>
                            <label class="u-mode-btn <?= $theme_mode === $val ? 'is-active' : '' ?>">
                                <input type="radio" name="u_theme_mode" value="<?= $val ?>"
                                       <?= $theme_mode === $val ? 'checked' : '' ?>>
                                <?= $lbl ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Auto mode -->
                <div id="color-mode-auto" class="color-mode-content <?= $manual_mode ? '' : 'active' ?>">
                    <div class="u-card">
                        <label>
                            Seed Hue:
                            <span class="seed-hue-preview" id="seed-hue-preview"
                                  style="background-color: hsl(<?= $v['seed-hue'] ?? '59' ?>, 70%, 50%)">
                            </span>
                            <input type="range" name="u_fields[seed-hue]"
                                   min="0" max="360"
                                   value="<?= $v['seed-hue'] ?? '59' ?>"
                                   oninput="this.nextElementSibling.value = this.value;
                                            document.getElementById('seed-hue-preview').style.backgroundColor =
                                                'hsl(' + this.value + ', 70%, 50%)'">
                            <o><?= $v['seed-hue'] ?? '59' ?></o>
                        </label>
                        <?php
                        $style_options = [
                            'graphite'   => 'Graphite',
                            'minimalist' => 'Minimalist',
                            'luxury'     => 'Luxury',
                            'pastoral'   => 'Pastoral',
                            'japane'     => 'Japane',
                            'vibrant'    => 'Vibrant',
                            'bold-dark'  => 'Bold Dark',
                            'neon'       => 'Neon',
                            'ocean'      => 'Ocean',
                            'sunset'     => 'Sunset',
                            'mono'       => 'Mono',
                        ];
                        ?>
                        <label>Style:
                            <select name="u_fields[style]">
                                <?php foreach ($style_options as $val => $lbl): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($v['style'] ?? '') === $val ? 'selected' : '' ?>>
                                        <?= $lbl ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>

                <!-- Manual mode -->
                <div id="color-mode-manual" class="color-mode-content <?= $manual_mode ? 'active' : '' ?>">
                    <h4>Brand Colors</h4>
                    <div class="color-table">
                        <div class="color-table-header">
                            <span>Color</span>
                            <span data-theme-col="light">Light</span>
                            <span data-theme-col="dark">Dark</span>
                        </div>

                        <?php
                        $color_rows = [
                            'primary' => 'Primary',
                            'accent'  => 'Accent',
                            'text'    => 'Text',
                            'bg'      => 'Background',
                            'section' => 'Section',
                            'H1'      => 'H1',
                            'border'  => 'Border',
                        ];
                        // Дефолты используются когда в файле нет ручного блока
                        // (т.е. значение — map.get-выражение или отсутствует)
                        $default_light = [
                            'color-primary-light' => '#3498db',
                            'color-accent-light'  => '#e74c3c',
                            'color-text-light'    => '#333333',
                            'color-bg-light'      => '#ffffff',
                            'color-section-light' => '#f5f5f5',
                            'color-H1-light'      => '#2c3e50',
                            'color-border-light'  => '#dddddd',
                        ];
                        $default_dark = [
                            'color-primary-dark' => '#2980b9',
                            'color-accent-dark'  => '#c0392b',
                            'color-text-dark'    => '#ffffff',
                            'color-bg-dark'      => '#1a1a1a',
                            'color-section-dark' => '#2a2a2a',
                            'color-H1-dark'      => '#5dade2',
                            'color-border-dark'  => '#444444',
                        ];

                        foreach ($color_rows as $key => $label):
                            $lk = "color-{$key}-light";
                            $dk = "color-{$key}-dark";
                            // get_current_values() уже отфильтровала map.get — безопасно
                            $vl = $v[$lk] ?? $default_light[$lk] ?? '#ffffff';
                            $vd = $v[$dk] ?? $default_dark[$dk]  ?? '#000000';
                        ?>
                            <div class="color-table-row <?= $key === 'H1' ? 'u-h1-row' : '' ?>">
                                <span class="color-label"><?= $label ?></span>
                                <div class="color-input-wrapper" data-theme-col="light">
                                    <?php if ($key === 'H1'): ?>
                                        <?php u_h1_gradient_field("u_fields[{$lk}]", $vl) ?>
                                    <?php else: ?>
                                        <?php u_color_field("u_fields[{$lk}]", $vl) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="color-input-wrapper" data-theme-col="dark">
                                    <?php if ($key === 'H1'): ?>
                                        <?php u_h1_gradient_field("u_fields[{$dk}]", $vd) ?>
                                    <?php else: ?>
                                        <?php u_color_field("u_fields[{$dk}]", $vd) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status Colors -->
                <div class="system-colors-section" id="status-colors-section">
                    <h4>Status Colors</h4>
                    <div class="color-table">
                        <div class="color-table-header">
                            <span>Color</span>
                            <span data-theme-col="light">Light</span>
                            <span data-theme-col="dark">Dark</span>
                        </div>
                        <?php
                        $system_state_rows = [
                            'success'     => ['label' => 'Success',      'light_default' => '#5db97a', 'dark_default' => '#4caf68'],
                            'warning'     => ['label' => 'Warning',      'light_default' => '#fcd34d', 'dark_default' => '#eab308'],
                            'error'       => ['label' => 'Error',        'light_default' => '#dc2f02', 'dark_default' => '#ff6b4a'],
                            'info'        => ['label' => 'Info',         'light_default' => '#4fc3f7', 'dark_default' => '#29b6f6'],
                            'callout-txt' => ['label' => 'Callout Text', 'light_default' => '#1a1a2e', 'dark_default' => '#e8e8f0'],
                        ];
                        foreach ($system_state_rows as $key => $cfg):
                            $lk = "color-{$key}-light";
                            $dk = "color-{$key}-dark";
                            $vl = $v[$lk] ?? $cfg['light_default'];
                            $vd = $v[$dk] ?? $cfg['dark_default'];
                        ?>
                            <div class="color-table-row">
                                <span class="color-label"><?= $cfg['label'] ?></span>
                                <div class="color-input-wrapper" data-theme-col="light">
                                    <?php u_color_field("u_fields[{$lk}]", $vl) ?>
                                </div>
                                <div class="color-input-wrapper" data-theme-col="dark">
                                    <?php u_color_field("u_fields[{$dk}]", $vd) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="u-desc u-mode-hint" style="margin-top:8px"></p>
                </div>
            </section>

            <!-- ── Site Info ──────────────────────────────────────────────── -->
            <section id="site-info" class="tab-pane">
                <h2>Site Info</h2>
                <?php
                $streams = [
                    'stream-1' => 'Stream 1',
                    'stream-2' => 'Stream 2',
                    'stream-3' => 'Stream 3',
                    'stream-4' => 'Stream 4',
                ];
                $subjects = [
                    'alpine_skiing', 'american_footbal', 'aussie_rules', 'badminton',
                    'baseball', 'basketball', 'biathlon', 'boxing', 'cricket',
                    'crosscountry', 'cycling', 'darts', 'english', 'esports',
                    'field_hockey', 'floorball', 'football', 'formula1', 'golf',
                    'greyhound_racing', 'handball', 'hockey', 'horse_racing', 'ice_hockey',
                    'mma', 'motorsport', 'padel', 'rugby', 'rugby_league', 'snooker',
                    'squash', 'table_tennis', 'tennis', 'volleyball', 'waterpolo',
                ];
                $cur_stream  = get_option('site_stream',  '');
                $cur_subject = get_option('site_subject', '');
                ?>
                <div class="u-card">
                    <div class="u-card-body">
                        <div class="u-card-left">

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Stream of Site</div>
                                <div class="u-basic-field-control">
                                    <select name="site_stream">
                                        <option value="">— not set —</option>
                                        <?php foreach ($streams as $val => $label): ?>
                                            <option value="<?= esc_attr($val) ?>"
                                                <?= selected($cur_stream, $val, false) ?>>
                                                <?= esc_html($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Subject of Site</div>
                                <div class="u-basic-field-control">
                                    <select name="site_subject">
                                        <option value="">— not set —</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <?php $label = ucwords(str_replace('_', ' ', $subject)); ?>
                                            <option value="<?= esc_attr($subject) ?>"
                                                <?= selected($cur_subject, $subject, false) ?>>
                                                <?= esc_html($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </section>

        </div>

    </form>
</div>
