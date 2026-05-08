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

    // ─── Font Vibe: смена картинки + описания ───────────────────────────────
    $(document).on('change', '#font-vibe-select', function () {
        var val      = $(this).val();
        var $preview = $('#font-vibe-preview');
        var baseUrl  = $preview.data('base-url');
        if (baseUrl) $preview.attr('src', baseUrl + val + '.webp');

        if (typeof uFontVibeDescs !== 'undefined' && uFontVibeDescs[val]) {
            $('#font-vibe-desc').text(uFontVibeDescs[val]);
        }
    });

    // ─── Radius Vibe: смена картинки + описания ─────────────────────────────
    $(document).on('change', '#radius-vibe-select', function () {
        var val      = $(this).val();
        var $preview = $('#radius-vibe-preview');
        var $placeholder = $preview.next('.u-preview-placeholder');
        var baseUrl  = $preview.data('base-url');
        if (baseUrl) {
            $preview.show().attr('src', baseUrl + val + '.webp');
            $placeholder.text($(this).find('option:selected').text()).hide();
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
        markDirty($(this));
    });

    // ─── Первичная инициализация ─────────────────────────────────────────────
    initColorPickers($('.tab-pane.active'));
});
