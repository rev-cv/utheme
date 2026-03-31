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
    $('.tab-btn').on('click', function (e) {
        e.preventDefault();
        $('.tab-btn').removeClass('active');
        $('.tab-pane').removeClass('active');
        $(this).addClass('active');
        var $target = $('#' + $(this).data('target'));
        $target.addClass('active');
        initColorPickers($target);
    });

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
        if (isManual) initColorPickers($('#color-mode-manual'));
    });

    // ─── Dirty-индикатор ────────────────────────────────────────────────────
    // Охватывает select, checkbox, radio, range — всё кроме служебных color-инпутов
    $(document).on('change', 'select, input[type="checkbox"], input[type="radio"], input[type="range"]', function () {
        markDirty($(this));
    });

    // ─── Смена картинки компонента при изменении селекта ────────────────────
    $(document).on('change', '.u-component-select', function () {
        var $preview = $(this).closest('.u-component-card').find('.u-component-preview-img');
        var baseUrl  = $preview.data('base-url');
        if (baseUrl) $preview.attr('src', baseUrl + $(this).val() + '.webp');
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

    // При изменении селекта toc-menu
    $(document).on('change', 'select[name="u_fields[toc-menu]"]', function () {
        updateTocIconVisibility($(this));
    });

    // ─── TOC Icon: кастомный dropdown ──────────────────────────────────────
    // Открыть/закрыть
    $(document).on('click', '#toc-icon-trigger', function (e) {
        e.stopPropagation();
        var $dd   = $('#toc-icon-dropdown');
        var $list = $('#toc-icon-list');
        var isOpen = $dd.hasClass('is-open');
        $dd.toggleClass('is-open', !isOpen);
        $list.toggle(!isOpen);
    });

    // Выбор опции
    $(document).on('click', '.u-icon-item', function () {
        var val  = $(this).data('value');
        var svg  = $(this).find('.u-icon-svg').html();
        var name = $(this).find('.u-icon-item-name').text();

        // Обновляем триггер
        $('#toc-icon-trigger .u-icon-svg').html(svg);
        $('#toc-icon-label').text(name);

        // Обновляем скрытый input
        $('#toc-icon-value').val(val);

        // Подсветка выбранного
        $('.u-icon-item').removeClass('is-selected');
        $(this).addClass('is-selected');

        // Закрываем
        $('#toc-icon-dropdown').removeClass('is-open');
        $('#toc-icon-list').hide();

        markDirty($('#toc-icon-value'));
    });

    // Закрываем при клике вне
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#toc-icon-dropdown').length) {
            $('#toc-icon-dropdown').removeClass('is-open');
            $('#toc-icon-list').hide();
        }
    });

    // ─── Первичная инициализация ─────────────────────────────────────────────
    initColorPickers($('.tab-pane.active'));
});
