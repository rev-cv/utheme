<?php
// ── Walker: десктопное горизонтальное меню ────────────────────────────────────
if (!class_exists('Hierarchical_Walker')) {
    class Hierarchical_Walker extends Walker_Nav_Menu {

        public function start_lvl(&$output, $depth = 0, $args = null) {
            $cls    = $depth === 0 ? 'sub-menu' : 'sub-sub-menu';
            $output .= '<ul class="' . $cls . '">';
        }

        public function end_lvl(&$output, $depth = 0, $args = null) {
            $output .= '</ul>';
        }

        public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
            $has = in_array('menu-item-has-children', (array) $item->classes);
            $cls = 'menu-item' . ($has ? ' has-children' : '');
            $output .= '<li class="' . esc_attr($cls) . '">';
            $output .= '<a href="' . esc_url($item->url ?: '#') . '">';
            $output .= '<span class="item-label">' . esc_html($item->title) . '</span>';
            if ($has) {
                // Стрелка вниз для корня, вправо для вложенных
                $pts    = $depth === 0 ? '6 9 12 15 18 9' : '9 18 15 12 9 6';
                $output .= '<svg class="item-chevron" viewBox="0 0 24 24" width="11" height="11"'
                         . ' stroke="currentColor" stroke-width="2.5" fill="none"'
                         . ' stroke-linecap="round" stroke-linejoin="round">'
                         . '<polyline points="' . $pts . '"></polyline></svg>';
            }
            $output .= '</a>';
        }

        public function end_el(&$output, $item, $depth = 0, $args = null) {
            $output .= '</li>';
        }
    }
}

// ── Walker: мобильная панель (аккордеон) ──────────────────────────────────────
if (!class_exists('Hierarchical_Panel_Walker')) {
    class Hierarchical_Panel_Walker extends Walker_Nav_Menu {

        public function start_lvl(&$output, $depth = 0, $args = null) {
            $cls    = $depth === 0 ? 'panel-sub-menu' : 'panel-sub-sub-menu';
            $output .= '<ul class="' . $cls . '">';
        }

        public function end_lvl(&$output, $depth = 0, $args = null) {
            $output .= '</ul>';
        }

        public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
            $has    = in_array('menu-item-has-children', (array) $item->classes);
            $output .= '<li class="panel-item">';
            $output .= '<div class="panel-item-row">';
            $output .= '<a href="' . esc_url($item->url ?: '#') . '">' . esc_html($item->title) . '</a>';
            if ($has) {
                $output .= '<button class="panel-toggle" aria-label="Раскрыть">'
                         . '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor"'
                         . ' stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">'
                         . '<polyline points="9 18 15 12 9 6"></polyline></svg>'
                         . '</button>';
            }
            $output .= '</div>';
        }

        public function end_el(&$output, $item, $depth = 0, $args = null) {
            $output .= '</li>';
        }
    }
}
?>

<header id="site-header">
    <div class="header-island">

        <div class="site-logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="site-name">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        </div>

        <div class="nav-wrapper">
            <?php wp_nav_menu([
                'theme_location'  => 'header-menu',
                'container'       => 'nav',
                'container_class' => 'side-nav',
                'menu_class'      => 'side-menu-list',
                'walker'          => new Hierarchical_Walker(),
                'depth'           => 2,
                'fallback_cb'     => false,
            ]); ?>
        </div>

        <button class="menu-toggle" aria-label="Открыть меню" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor"
                 stroke-width="2" fill="none" stroke-linecap="round">
                <line x1="3" y1="6"  x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

    </div>
</header>

<div class="side-panel" id="hier-side-panel" aria-hidden="true">
    <div class="panel-header">
        <span class="panel-site-name"><?php bloginfo('name'); ?></span>
        <button class="panel-close" aria-label="Закрыть меню">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor"
                 stroke-width="2" fill="none" stroke-linecap="round">
                <line x1="18" y1="6"  x2="6"  y2="18"></line>
                <line x1="6"  y1="6"  x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <nav class="panel-nav">
        <?php wp_nav_menu([
            'theme_location' => 'header-menu',
            'container'      => false,
            'menu_class'     => 'panel-menu-list',
            'walker'         => new Hierarchical_Panel_Walker(),
            'depth'          => 3,
            'fallback_cb'    => false,
        ]); ?>
    </nav>
</div>

<div class="menu-overlay" id="hier-overlay"></div>

<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var header   = document.getElementById('site-header');
        var toggle   = document.querySelector('.menu-toggle');
        var panel    = document.getElementById('hier-side-panel');
        var overlay  = document.getElementById('hier-overlay');
        var closeBtn = panel ? panel.querySelector('.panel-close') : null;
        var body     = document.body;

        // ── Переключение десктоп / мобайл ─────────────────────────────────────

        function positionDropdowns() {
            var items = header ? header.querySelectorAll('.side-menu-list > .menu-item') : [];
            items.forEach(function (item) {
                var sub = item.querySelector(':scope > .sub-menu');
                if (!sub) return;

                item.classList.remove('sub-flip');
                if (item.getBoundingClientRect().left + sub.offsetWidth > window.innerWidth) {
                    item.classList.add('sub-flip');
                }

                sub.querySelectorAll(':scope > .menu-item').forEach(function (subItem) {
                    var subSub = subItem.querySelector(':scope > .sub-sub-menu');
                    if (!subSub) return;
                    subItem.classList.remove('sub-sub-flip');
                    if (subItem.getBoundingClientRect().right + subSub.offsetWidth > window.innerWidth) {
                        subItem.classList.add('sub-sub-flip');
                    }
                });
            });
        }

        function updateLayout() {
            if (!header) return;
            var island = header.querySelector('.header-island');
            if (!island) return;
            header.classList.add('is-desktop');
            void island.offsetWidth; // force reflow
            var menuList = island.querySelector('.side-menu-list');
            var fits = !menuList
                || menuList.getBoundingClientRect().right <= island.getBoundingClientRect().right + 1;
            header.classList.toggle('is-desktop', fits);
            if (fits) {
                closePanel();
                positionDropdowns();
            }
        }

        // ── Открытие / закрытие панели ────────────────────────────────────────

        function openPanel() {
            body.classList.add('menu-open');
            panel.classList.add('is-active');
            panel.setAttribute('aria-hidden', 'false');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            body.classList.remove('menu-open');
            panel.classList.remove('is-active');
            panel.setAttribute('aria-hidden', 'true');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }

        if (toggle)   toggle.addEventListener('click', openPanel);
        if (closeBtn) closeBtn.addEventListener('click', closePanel);
        if (overlay)  overlay.addEventListener('click', closePanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && body.classList.contains('menu-open')) closePanel();
        });

        // ── Аккордеон мобильного меню ─────────────────────────────────────────

        if (panel) {
            panel.addEventListener('click', function (e) {
                var btn = e.target.closest('.panel-toggle');
                if (!btn) return;
                var li  = btn.closest('.panel-item');
                if (!li) return;
                var sub = li.querySelector(':scope > .panel-sub-menu, :scope > .panel-sub-sub-menu');
                if (!sub) return;
                var isOpen = btn.classList.contains('is-open');
                btn.classList.toggle('is-open', !isOpen);
                sub.classList.toggle('is-open', !isOpen);
                btn.setAttribute('aria-expanded', String(!isOpen));
            });
        }

        // ── Отслеживание изменения размеров ───────────────────────────────────

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(updateLayout).observe(document.documentElement);
        } else {
            var resizeTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateLayout, 120);
            });
        }

        // ── Первый рендер ─────────────────────────────────────────────────────

        updateLayout();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(updateLayout);
        }
    });
})();
</script>
