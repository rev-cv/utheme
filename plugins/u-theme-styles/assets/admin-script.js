jQuery(document).ready(function ($) {

    // ─── Нативный цветовой пикер ────────────────────────────────────────────
    function initColorPickers($container) {
        $container.find('.u-color-field').each(function () {
            var $field = $(this);
            if ($field.data('ucp-init')) return;
            $field.data('ucp-init', true);

            var $colorInput = $field.find('.u-color-native');
            var $textInput  = $field.find('.u-color-hex');
            var $swatch     = $field.find('.u-color-swatch');
            var $hidden     = $field.find('.u-color-picker');

            function applyColor(hex) {
                hex = normalizeHex(hex);
                if (!hex) return;
                $colorInput.val(hex);
                $textInput.val(hex);
                $swatch.css('background-color', hex);
                $hidden.val(hex);
                markDirty($field);
            }

            $colorInput.on('input change', function () { applyColor(this.value); });
            $textInput.on('blur', function () { applyColor(this.value); });
            $textInput.on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); applyColor(this.value); }
            });
            $swatch.on('click', function () { $colorInput.trigger('click'); });
        });
    }

    function normalizeHex(str) {
        str = str.trim().replace(/^#+/, '');
        if (str.length === 3) str = str.split('').map(function(c){ return c+c; }).join('');
        if (str.length === 6 && /^[0-9a-fA-F]{6}$/.test(str)) return '#' + str;
        return null;
    }

    function markDirty($el) {
        var paneId = $el.closest('.tab-pane').attr('id');
        if (paneId) $('.tab-btn[data-target="' + paneId + '"]').addClass('dirty');
    }

    // ─── Переключение вкладок ───────────────────────────────────────────────
    function activateTab(tabId) {
        var $btn = $('.tab-btn[data-target="' + tabId + '"]');
        if (!$btn.length) return;
        $('.tab-btn').removeClass('active');
        $('.tab-pane').removeClass('active');
        $btn.addClass('active');
        var $target = $('#' + tabId);
        $target.addClass('active');
        initColorPickers($target);
    }

    $('.tab-btn').on('click', function (e) {
        e.preventDefault();
        var tabId = $(this).data('target');
        activateTab(tabId);
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        history.replaceState(null, '', url.toString());
    });

    // Восстановление вкладки из URL при загрузке
    (function () {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        if (tab && $('.tab-btn[data-target="' + tab + '"]').length) {
            activateTab(tab);
        }
    })();

    // ─── Применить hex-цвет ко всем элементам u-color-field ────────────────
    function setColorField(name, hex) {
        // Для градиентного поля — ставим solid-режим с авто-цветом
        var $gradField = $('[name="' + name + '"]').closest('.u-gradient-field');
        if ($gradField.length) {
            $gradField.attr('data-mode', 'solid');
            $gradField.find('.u-gradient-mode-r[value="solid"]').prop('checked', true);
            $gradField.find('.u-gradient-toggle-btn').removeClass('is-active');
            $gradField.find('.u-gradient-mode-r[value="solid"]').closest('.u-gradient-toggle-btn').addClass('is-active');
            $gradField.find('.u-gradient-extra').addClass('is-hidden');
            applyGradC1($gradField, hex);
            return;
        }
        var $field = $('[name="' + name + '"]').closest('.u-color-field');
        if (!$field.length) return;
        $field.find('.u-color-native').val(hex);
        $field.find('.u-color-hex').val(hex);
        $field.find('.u-color-swatch').css('background-color', hex);
        $field.find('.u-color-picker').val(hex);
    }

    // ─── Auto / Manual переключатель ────────────────────────────────────────
    $('#color-mode-toggle').on('change', function () {
        var isManual = this.checked;
        $('#color-mode-auto').toggleClass('active', !isManual);
        $('#color-mode-manual').toggleClass('active', isManual);
        $('#color-mode-status')
            .toggleClass('auto',   !isManual)
            .toggleClass('manual',  isManual)
            .text(isManual ? 'Manual' : 'Auto');
        $('.tab-btn[data-target="colors"]').addClass('dirty');

        if (isManual) {
            initColorPickers($('#color-mode-manual'));

            // Если в conf.scss нет сохранённых ручных цветов — подгружаем сгенерированные
            if (typeof uThemeData !== 'undefined' && !uThemeData.isManual) {
                var $manual = $('#color-mode-manual');
                $manual.addClass('u-colors-loading');
                $.post(uThemeData.ajaxUrl, {
                    action: 'u_theme_preview_colors',
                    nonce:  uThemeData.nonce
                })
                .done(function (resp) {
                    if (resp.success && resp.data) {
                        $.each(resp.data, function (key, hex) {
                            setColorField('u_fields[' + key + ']', hex);
                        });
                    }
                })
                .always(function () {
                    $manual.removeClass('u-colors-loading');
                });
            }
        }
    });

    // ─── Dirty-индикатор ────────────────────────────────────────────────────
    // Охватывает select, checkbox, radio, range — всё кроме служебных color-инпутов
    $(document).on('change', 'select, input[type="checkbox"], input[type="radio"], input[type="range"]', function () {
        markDirty($(this));
    });

    // ─── Смена картинки компонента при изменении селекта ────────────────────
    $(document).on('change', '.u-component-select', function () {
        var $card    = $(this).closest('.u-component-card');
        var $right   = $card.find('.u-card-right');
        var $preview = $card.find('.u-component-preview-img');
        var baseUrl  = $preview.data('base-url');
        if (baseUrl) {
            $right.show();
            $preview.show().attr('src', baseUrl + $(this).val() + '.webp');
        }
    });

    // ─── Font Vibe: смена шрифта + загрузка Google Fonts + описание ────────
    $(document).on('change', '#font-vibe-select', function () {
        var val      = $(this).val();
        var $preview = $('#font-vibe-preview');

        if (typeof uFontVibeFamilies !== 'undefined' && uFontVibeFamilies[val]) {
            var fam = uFontVibeFamilies[val];
            $preview[0].style.setProperty('--fpb-hd', fam.hd);
            $preview[0].style.setProperty('--fpb-txt', fam.txt);
            if (fam.gf) {
                var id = 'gf-fv-' + val;
                if (!document.getElementById(id)) {
                    var link = document.createElement('link');
                    link.id = id;
                    link.rel = 'stylesheet';
                    link.href = fam.gf;
                    document.head.appendChild(link);
                }
            }
        }

        if (typeof uFontVibeDescs !== 'undefined' && uFontVibeDescs[val]) {
            $('#font-vibe-desc').text(uFontVibeDescs[val]);
        }
    });

    // ─── Radius Vibe: смена border-radius + описания ────────────────────────
    $(document).on('change', '#radius-vibe-select', function () {
        var val = $(this).val();
        if (typeof uRadiusVibeCss !== 'undefined' && uRadiusVibeCss[val]) {
            $('#radius-vibe-preview').css('border-radius', uRadiusVibeCss[val]);
        }
        if (typeof uRadiusVibeDescs !== 'undefined' && uRadiusVibeDescs[val] !== undefined) {
            $('#radius-vibe-desc').text(uRadiusVibeDescs[val]);
        }
    });

    // ─── TOC Icon: показываем только при выборе опции "icon" ────────────────
    function updateTocIconVisibility($select) {
        var $card = $select.closest('.u-component-card');
        var $wrap = $card.find('[data-toc-icon-wrap]');
        if (!$wrap.length) return;
        if ($select.val() === 'icon') {
            $wrap.slideDown(150);
        } else {
            $wrap.slideUp(150);
        }
    }

    $(document).on('change', 'select[name="u_fields[toc-menu]"]', function () {
        updateTocIconVisibility($(this));
    });

    // ─── Icon dropdowns: универсальный обработчик ─────────────────────────
    // Открыть/закрыть
    $(document).on('click', '.u-icon-trigger', function (e) {
        e.stopPropagation();
        var $dd    = $(this).closest('.u-icon-dropdown');
        var $list  = $dd.find('.u-icon-list');
        var isOpen = $dd.hasClass('is-open');
        // Закрываем другие открытые дропдауны
        $('.u-icon-dropdown.is-open').not($dd).removeClass('is-open').find('.u-icon-list').hide();
        $dd.toggleClass('is-open', !isOpen);
        $list.toggle(!isOpen);
    });

    // Выбор опции
    $(document).on('click', '.u-icon-item', function () {
        var $item      = $(this);
        var val        = $item.data('value');
        var svg        = $item.find('.u-icon-svg').html();
        var name       = $item.find('.u-icon-item-name').text();
        var $dd        = $item.closest('.u-icon-dropdown');
        var inputName  = $dd.data('input-name');

        $dd.find('.u-icon-trigger .u-icon-svg').html(svg);
        $dd.find('.u-icon-trigger-name').text(name);
        $dd.find('.u-icon-item').removeClass('is-selected');
        $item.addClass('is-selected');
        $dd.removeClass('is-open').find('.u-icon-list').hide();

        var $hidden = $('input[name="u_fields[' + inputName + ']"]');
        $hidden.val(val);
        markDirty($hidden);
    });

    // Закрываем при клике вне
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.u-icon-dropdown').length) {
            $('.u-icon-dropdown').removeClass('is-open').find('.u-icon-list').hide();
        }
    });

    // ─── Theme Mode: блокировка неактивных колонок ───────────────────────────
    // Применяется ко ВСЕМ [data-theme-col] на странице:
    // и к Brand Colors таблице, и к Status Colors таблице.
    var modeHints = {
        'dark-only':  'Dark Only: колонка Light перекрывается MODE-блоком и не влияет на результат.',
        'light-only': 'Light Only: колонка Dark перекрывается MODE-блоком и не влияет на результат.',
        'both':       '',
    };

    function updateThemeModeColumns(mode) {
        $('[data-theme-col]').removeClass('u-theme-col-inactive u-theme-col-active');
        if (mode === 'dark-only') {
            $('[data-theme-col="light"]').addClass('u-theme-col-inactive');
            $('[data-theme-col="dark"]').addClass('u-theme-col-active');
        } else if (mode === 'light-only') {
            $('[data-theme-col="dark"]').addClass('u-theme-col-inactive');
            $('[data-theme-col="light"]').addClass('u-theme-col-active');
        }
        $('.u-mode-hint').text(modeHints[mode] || '');
    }

    $(document).on('change', 'input[name="u_theme_mode"]', function () {
        $('.u-mode-btn').removeClass('is-active');
        $(this).closest('.u-mode-btn').addClass('is-active');
        updateThemeModeColumns($(this).val());
        markDirty($(this));
    });

    // Инициализация при загрузке страницы
    var initialMode = (typeof uThemeData !== 'undefined' && uThemeData.themeMode)
        ? uThemeData.themeMode
        : ($('input[name="u_theme_mode"]:checked').val() || 'both');
    updateThemeModeColumns(initialMode);

    // ─── Typography presets ──────────────────────────────────────────────────
    var uTypoPresets = {
        soft:     { 'hd-weight': 500,  'hd-height': 1.20, 'hd-ls': -0.020, 'hd-case': 'none',      'txt-weight': 400, 'txt-height': 1.55, 'txt-ls': 0 },
        impact:   { 'hd-weight': 900,  'hd-height': 1.00, 'hd-ls': -0.020, 'hd-case': 'uppercase', 'txt-weight': 400, 'txt-height': 1.55, 'txt-ls': 0 },
        monolith: { 'hd-weight': 700,  'hd-height': 1.05, 'hd-ls': -0.050, 'hd-case': 'uppercase', 'txt-weight': 400, 'txt-height': 1.55, 'txt-ls': 0 },
        open:     { 'hd-weight': 400,  'hd-height': 1.40, 'hd-ls':  0.020, 'hd-case': 'none',      'txt-weight': 400, 'txt-height': 1.65, 'txt-ls': 0 },
    };

    // Форматирует числовое значение как em-строку без лишних нулей: 0.020 → "0.02em"
    window.fmtEm = function (val) {
        return parseFloat(val).toFixed(3).replace(/(\.\d*[1-9])0+$/, '$1').replace(/\.0+$/, '') + 'em';
    };

    function setTypoSlider(inputName, outputId, value, isEm) {
        var $input = $('[name="u_fields[' + inputName + ']"]');
        $input.val(isEm ? parseFloat(value).toFixed(3) : value);
        $('#' + outputId).text(isEm ? fmtEm(value) : value);
    }

    function uSyncFontPreview() {
        var el = document.getElementById('font-vibe-preview');
        if (!el) return;
        el.style.setProperty('--fpb-hd-w',     $('[name="u_fields[hd-weight]"]').val());
        el.style.setProperty('--fpb-hd-lh',    fmtEm($('[name="u_fields[hd-height]"]').val()));
        el.style.setProperty('--fpb-hd-ls',    fmtEm($('[name="u_fields[hd-letter-spacing]"]').val()));
        el.style.setProperty('--fpb-hd-case',  $('[name="u_fields[hd-case]"]').val());
        el.style.setProperty('--fpb-hd-style', $('[name="u_fields[hd-italic]"]').val());
        el.style.setProperty('--fpb-txt-w',    $('[name="u_fields[txt-weight]"]').val());
        el.style.setProperty('--fpb-txt-lh',   fmtEm($('[name="u_fields[txt-height]"]').val()));
        el.style.setProperty('--fpb-txt-ls',   fmtEm($('[name="u_fields[txt-letter-spacing]"]').val()));
    }

    $(document).on('input',  '[name="u_fields[hd-weight]"], [name="u_fields[hd-height]"], [name="u_fields[hd-letter-spacing]"], [name="u_fields[txt-weight]"], [name="u_fields[txt-height]"], [name="u_fields[txt-letter-spacing]"]', uSyncFontPreview);
    $(document).on('change', '[name="u_fields[hd-case]"], [name="u_fields[hd-italic]"]', uSyncFontPreview);

    $(document).on('click', '.u-typo-preset', function () {
        var p = uTypoPresets[$(this).data('preset')];
        if (!p) return;
        setTypoSlider('hd-weight',         'hd-weight-output',  p['hd-weight'],  false);
        setTypoSlider('hd-height',         'hd-height-output',  p['hd-height'],  true);
        setTypoSlider('hd-letter-spacing', 'hd-ls-output',      p['hd-ls'],      true);
        setTypoSlider('txt-weight',        'txt-weight-output', p['txt-weight'], false);
        setTypoSlider('txt-height',        'txt-height-output', p['txt-height'], true);
        setTypoSlider('txt-letter-spacing','txt-ls-output',     p['txt-ls'],     true);
        $('[name="u_fields[hd-case]"]').val(p['hd-case']);
        uSyncFontPreview();
        markDirty($(this));
    });

    // ─── H1 Gradient fields ──────────────────────────────────────────────────
    function syncGradientField($field) {
        var mode  = $field.attr('data-mode') || 'solid';
        var c1    = $field.find('.u-grad-c1-hex').val() || '#cccccc';
        var c2    = $field.find('.u-grad-c2-hex').val() || '#ffffff';
        var angle = $field.find('.u-gradient-angle-r').val() || '135';
        var value = mode === 'gradient'
                    ? 'linear-gradient(' + angle + 'deg, ' + c1 + ', ' + c2 + ')'
                    : c1;
        $field.find('.u-gradient-value').val(value);
        $field.find('.u-gradient-strip').css('background',
            'linear-gradient(' + angle + 'deg, ' + c1 + ', ' + c2 + ')');
    }

    function applyGradC1($field, raw) {
        var hex = normalizeHex(raw);
        if (!hex) return;
        $field.find('.u-grad-c1-native').val(hex);
        $field.find('.u-grad-c1-hex').val(hex);
        $field.find('.u-grad-c1-swatch').css('background-color', hex);
        syncGradientField($field);
        markDirty($field);
    }

    function applyGradC2($field, raw) {
        var hex = normalizeHex(raw);
        if (!hex) return;
        $field.find('.u-grad-c2-native').val(hex);
        $field.find('.u-grad-c2-hex').val(hex);
        $field.find('.u-grad-c2-swatch').css('background-color', hex);
        syncGradientField($field);
        markDirty($field);
    }

    // Mode toggle
    $(document).on('click', '.u-gradient-toggle-btn', function () {
        var $btn   = $(this);
        var $field = $btn.closest('.u-gradient-field');
        var mode   = $btn.find('.u-gradient-mode-r').val();
        $field.attr('data-mode', mode);
        $field.find('.u-gradient-extra').toggleClass('is-hidden', mode !== 'gradient');
        $field.find('.u-gradient-toggle-btn').removeClass('is-active');
        $btn.addClass('is-active');
        syncGradientField($field);
        markDirty($field);
    });

    // C1 color
    $(document).on('input change', '.u-grad-c1-native', function () {
        applyGradC1($(this).closest('.u-gradient-field'), this.value);
    });
    $(document).on('blur', '.u-grad-c1-hex', function () {
        applyGradC1($(this).closest('.u-gradient-field'), this.value);
    });
    $(document).on('keydown', '.u-grad-c1-hex', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); applyGradC1($(this).closest('.u-gradient-field'), this.value); }
    });
    $(document).on('click', '.u-grad-c1-swatch', function () {
        $(this).closest('.u-color-field').find('.u-grad-c1-native').trigger('click');
    });

    // C2 color
    $(document).on('input change', '.u-grad-c2-native', function () {
        applyGradC2($(this).closest('.u-gradient-field'), this.value);
    });
    $(document).on('blur', '.u-grad-c2-hex', function () {
        applyGradC2($(this).closest('.u-gradient-field'), this.value);
    });
    $(document).on('keydown', '.u-grad-c2-hex', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); applyGradC2($(this).closest('.u-gradient-field'), this.value); }
    });
    $(document).on('click', '.u-grad-c2-swatch', function () {
        $(this).closest('.u-color-field').find('.u-grad-c2-native').trigger('click');
    });

    // Angle slider
    $(document).on('input', '.u-gradient-angle-r', function () {
        var $field = $(this).closest('.u-gradient-field');
        $(this).siblings('.u-gradient-angle-out').text(this.value + '°');
        syncGradientField($field);
        markDirty($field);
    });

    // ─── Первичная инициализация ─────────────────────────────────────────────
    initColorPickers($('.tab-pane.active'));
});
