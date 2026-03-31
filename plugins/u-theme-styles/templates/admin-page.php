<?php
/**
 * Вспомогательная функция: нативный цветовой пикер.
 */
function u_color_field(string $name, string $value): void {
    // Защищаемся от map.get-выражений (авто-режим) — показываем дефолт
    if (str_contains($value, '(') || str_contains($value, 'map.')) {
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
?>

<div class="wrap u-config-wrapper">
    <h1>U Theme Styles</h1>
    <form method="post" id="u-theme-form">
        <?php wp_nonce_field('u_theme_update'); ?>

        <nav class="tabs-nav">
            <button class="tab-btn active" data-target="basic">Basic</button>
            <button class="tab-btn" data-target="components">Components</button>
            <button class="tab-btn" data-target="colors">Colors</button>
            <div class="tabs-nav-spacer"></div>
            <button type="submit" class="button button-primary tabs-save-btn">Save Settings</button>
        </nav>

        <div class="tabs-content">

            <!-- ── Basic ──────────────────────────────────────────────────── -->
            <section id="basic" class="tab-pane active">
                <h2>Basic Settings</h2>
                <?php
                $font_vibes = [
                    'google' => [
                        'label' => 'Google',  
                        'desc' => 'Дружелюбие, мягкость, инновации. Идеальное решение для экосистемных продуктов и интерфейсов, ориентированных на пользователя. Создает ощущение доступности, простоты и технологичного комфорта..'
                    ],
                    'strict' => [
                        'label' => 'Strict',
                        'desc' => 'Дисциплина, точность, функциональность. Отлично подходит для дашбордов, CRM-систем и инструментов разработки. Подчеркивает аналитический характер продукта и фокус на данных.'
                    ],
                    'editorial' => [
                        'label' => 'Editorial',
                        'desc' => 'Эстетика, глубина, интеллектуальность. Превосходно подходит для лонгридов, медиа-проектов или брендов в сфере lifestyle. Передает атмосферу дорогого печатного издания и уважения к качественному контенту.'
                    ],
                    'startup' => [
                        'label' => 'Startup',
                        'desc' => 'Чистота, контроль, современность. Идеально для IT-сервисов и SaaS. Вызывает доверие и ощущение «отточенного» продукта.'
                    ],
                    'space' => [
                        'label' => 'Space',
                        'desc' => 'Открытость, дружелюбие. Широкие буквы Montserrat дают ощущение масштаба, а Open Sans легко читается в длинных текстах.'
                    ],
                    'syntax' => [
                        'label' => 'Syntax',
                        'desc' => 'Инженерная эстетика. Использование моноширинного шрифта в заголовках намекает на код, данные и точность.'
                    ],
                    'neo-swiss' => [
                        'label' => 'Neo Swiss',
                        'desc' => 'Функциональность без эмоций. Стиль швейцарской школы дизайна. Ничего лишнего, только информация.'
                    ],
                    'engineer' => [
                        'label' => 'Engineer',
                        'desc' => 'Дисциплина, точность, наследие. Это стиль чертежей, патентных бюро и серьезных лонгридов. Он выглядит так, будто информацию тщательно структурировали и проверили трижды.'
                    ],
                    'vogue' => [
                        'label' => 'Vogue',
                        'desc' => 'Роскошь, классика, высокий чек. Ассоциируется с модой, дорогими отелями и ювелирными брендами.'
                    ],
                    'boutique' => [
                        'label' => 'Boutique',
                        'desc' => 'Изящество и воздух. Тонкие засечки заголовка выглядят как искусство, а Montserrat придает современности.'
                    ],
                    'wisdom' => [
                        'label' => 'Wisdom',
                        'desc' => 'Античность, история. Для сайтов с глубоким смыслом: виноделие, архитектура, философия или дорогие аксессуары.'
                    ],
                    'noble' => [
                        'label' => 'Noble',
                        'desc' => 'Крафтовый премиум. Мягкие, «вкусные» формы Fraunces создают уютное, но очень дорогое ощущение (например, органика, кофе, мебель).'
                    ],
                    'manuscript' => [
                        'label' => 'Manuscript',
                        'desc' => ''
                    ],
                    'brutal' => [
                        'label' => 'Brutal',
                        'desc' => 'Энергия, бескомпромиссность, акцент. Лучший выбор для промо-страниц, креативных агентств или манифестов. Выглядит громко и уверенно, мгновенно захватывая внимание за счет массивных заголовков.'
                    ],
                    'urban' => [
                        'label' => 'Urban',
                        'desc' => 'Сила, агрессия, скорость. Уличная мода, спорт, энергетические напитки. Заголовки «бьют» в глаза.'
                    ],
                    'manifesto' => [
                        'label' => 'Manifesto',
                        'desc' => 'Газетный напор. Узкие и высокие заголовки напоминают плакаты или заголовки новостей. Вызывает чувство срочности и важности.'
                    ],
                    'black-metal' => [
                        'label' => 'Black Metal',
                        'desc' => 'Техно-футуризм. Широкие, массивные буквы Unbounded выглядят как нечто из будущего или ночного клуба.'
                    ],
                    'raw' => [
                        'label' => 'Raw',
                        'desc' => 'Диджитал-арт. Немного «сломанные» формы букв создают ощущение эксперимента, креативного агентства или крипто-проекта.'
                    ],
                    'velocity' => [
                        'label' => 'Velocity',
                        'desc' => 'Скорость, напор и мощь. Большие буквы в заголовках создают эффект громкого заявления, не оставляя места для сомнений.'
                    ],
                    'courtside' => [
                        'label' => 'Courtside',
                        'desc' => ''
                    ],
                    'district' => [
                        'label' => 'District',
                        'desc' => ''
                    ],
                    'blast' => [
                        'label' => 'Blast',
                        'desc' => ''
                    ],
                    'industry' => [
                        'label' => 'Industry',
                        'desc' => ''
                    ],
                    'overdrive' => [
                        'label' => 'Overdrive',
                        'desc' => 'Энергия, плакатность, уверенность. Это классика современного маркетинга. Выглядит как заголовок крутого YouTube-канала, афиша блокбастера или лендинг фитнес-клуба.'
                    ],
                    'organic' => [
                        'label' => 'Organic',
                        'desc' => 'Мягкость, доброта, экология. Округлые формы успокаивают. Идеально для детских товаров или товаров для йоги и здоровья.'
                    ],
                    'vintage' => [
                        'label' => 'Vintage',
                        'desc' => 'Надежность прошлого. Напоминает старые книги или качественную журналистику 70-х. Вызывает ностальгию и доверие.'
                    ],
                    'interface' => [
                        'label' => 'Interface',
                        'desc' => ''
                    ],
                    'antidesign' => [
                        'label' => 'Anti Design',
                        'desc' => 'Ироничный примитивизм. Выглядит «никак», и в этом его сила. Для тех, кто настолько крут, что ему не нужен дизайн (арт-галереи, модные фотографы).'
                    ],
                ];
                $font_sizes = range(14, 22);
                $current_font_vibe = $v['font-vibe'] ?? 'neo-swiss';
                ?>
                <div class="u-card">
                    <div class="u-card-body">
                        <div class="u-card-left">

                            <div class="u-basic-field">
                                <div class="u-basic-field-label">Font Size</div>
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
                                    <label class="u-checkbox-label">
                                        <input type="checkbox" name="u_fields[is-img_contain]" value="true"
                                            <?= ($v['is-img_contain'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                        <span>
                                            <strong>Contain Images</strong>
                                            <span class="u-desc">Изображения вписываются в блок целиком (object-fit: contain) без обрезки по краям.</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="u-card-right">
                            <div class="u-preview u-preview--tall">
                                <img src="<?= plugins_url("assets/media/font-vibe-{$current_font_vibe}.webp", dirname(__FILE__)) ?>"
                                     data-base-url="<?= plugins_url('assets/media/font-vibe-', dirname(__FILE__)) ?>"
                                     alt="Font preview"
                                     class="u-font-preview-img u-component-preview-img"
                                     id="font-vibe-preview">
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

                $components = [
                    'main-menu' => [
                        'title'       => 'Main Menu',
                        'desc'        => 'Стиль отображения главного меню сайта.',
                        'options'     => ['island', 'aside', 'marquee', 'boring', 'docs', 'circle', 'newspaper', 'console', 'dynamic'],
                        'switch'      => 'is-menu-title',
                        'switch_desc' => 'Показывать название сайта в главном меню (только для boring)',
                    ],
                    'footer-menu' => [
                        'title'   => 'Footer Menu',
                        'desc'    => 'Стиль отображения меню в подвале сайта.<br><b>2columns</b> — два столбца со ссылками.<br><b>Central</b> — горизонтальный ряд по центру.',
                        'options' => ['2columns', 'central'],
                    ],
                    'toc-menu' => [
                        'title'       => 'Table of Contents',
                        'desc'        => 'Оглавление статьи.<br><b>Circle</b> — круглые маркеры.<br><b>Number</b> — нумерованный список. <br><b>Icon</b> — иконки перед пунктами.',
                        'options'     => ['circle', 'number', 'icon'],
                        'switch'      => 'is-not-section',
                        'switch_desc' => 'Отобразить оглавление без секции',
                        'icon_select' => true,
                    ],
                    'article-card' => [
                        'title'   => 'Article Card',
                        'desc'    => 'Внешний вид карточки статьи в списках и архивах.',
                        'options' => ['default', 'frame', 'slide', 'windows', 'float', 'soft', 'split'],
                    ],
                ];

                foreach ($components as $id => $data): ?>
                    <div class="u-component-card">
                        <div class="u-card-header">
                            <h3><?= $data['title'] ?></h3>
                            <?php if (isset($data['options'])): ?>
                                <select name="u_fields[<?= $id ?>]" class="u-component-select">
                                    <?php foreach ($data['options'] as $opt): ?>
                                        <option value="<?= $opt ?>"
                                            <?= ($v[$id] ?? $data['options'][0]) === $opt ? 'selected' : '' ?>>
                                            <?= ucfirst($opt) ?>
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
                                        <div class="u-icon-dropdown" id="toc-icon-dropdown">
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

                                <p class="u-desc"><?= $data['desc'] ?? '' ?></p>
                            </div>

                            <div class="u-card-right">
                                <div class="u-preview">
                                    <img src="<?= plugins_url("assets/media/{$id}-" . ($v[$id] ?? $data['options'][0] ?? $id) . ".webp", dirname(__FILE__)) ?>"
                                         data-base-url="<?= plugins_url("assets/media/{$id}-", dirname(__FILE__)) ?>"
                                         alt="<?= esc_attr($data['title']) ?>"
                                         class="u-component-preview-img">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                            'luxury'     => 'Luxury',
                            'minimalist'    => 'Minimalist',
                            'vibrant' => 'Vibrant',
                            'graphite' => 'Graphite',
                            'pastoral' => 'Pastoral',
                            'japane' => 'Japane',
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
                    <div class="color-table">
                        <div class="color-table-header">
                            <span>Color</span>
                            <span>Light</span>
                            <span>Dark</span>
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
                            <div class="color-table-row">
                                <span class="color-label"><?= $label ?></span>
                                <div class="color-input-wrapper">
                                    <?php u_color_field("u_fields[{$lk}]", $vl) ?>
                                </div>
                                <div class="color-input-wrapper">
                                    <?php u_color_field("u_fields[{$dk}]", $vd) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- System States -->
                <div class="system-colors-section">
                    <h4>System States</h4>
                    <div class="system-colors-grid">
                        <?php
                        $system_colors = [
                            'color-success' => ['label' => 'Success', 'default' => '#5db97a'],
                            'color-warning' => ['label' => 'Warning', 'default' => '#fcd34d'],
                            'color-error'   => ['label' => 'Error',   'default' => '#dc2f02'],
                            'color-info'    => ['label' => 'Info',    'default' => '#4fc3f7'],
                        ];
                        foreach ($system_colors as $key => $cfg): ?>
                            <div class="system-color-item">
                                <label><?= $cfg['label'] ?></label>
                                <?php u_color_field("u_fields[{$key}]", $v[$key] ?? $cfg['default']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

        </div>

        <input type="hidden" name="u_save_scss" value="1">
    </form>
</div>
