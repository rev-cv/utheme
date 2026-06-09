<?php
// ── Walker: десктопное горизонтальное меню ────────────────────────────────────
if (!class_exists('Hierarchical_Walker')) {
    class Hierarchical_Walker extends Walker_Nav_Menu {

        public function start_lvl(&$output, $depth = 0, $args = null) {
            $cls    = $depth === 0 ? 'ut-item__sub' : 'ut-item__sub-sub';
            $output .= '<ul class="' . $cls . '">';
        }

        public function end_lvl(&$output, $depth = 0, $args = null) {
            $output .= '</ul>';
        }

        public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
            $has = in_array('menu-item-has-children', (array) $item->classes);
            $cls = 'ut-nav__item' . ($has ? ' ut-nav__item--has-sub' : '');
            $output .= '<li class="' . esc_attr($cls) . '">';
            $output .= '<a href="' . esc_url($item->url ?: '#') . '">';
            $output .= '<span class="ut-item__label">' . esc_html($item->title) . '</span>';
            if ($has) {
                $pts    = $depth === 0 ? '6 9 12 15 18 9' : '9 18 15 12 9 6';
                $output .= '<svg class="ut-item__chevron" viewBox="0 0 24 24" width="11" height="11"'
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
            $cls    = $depth === 0 ? 'ut-panel__sub' : 'ut-panel__sub-sub';
            $output .= '<ul class="' . $cls . '">';
        }

        public function end_lvl(&$output, $depth = 0, $args = null) {
            $output .= '</ul>';
        }

        public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
            $has    = in_array('menu-item-has-children', (array) $item->classes);
            $output .= '<li class="ut-panel__item">';
            $output .= '<div class="ut-item__row">';
            $output .= '<a href="' . esc_url($item->url ?: '#') . '">' . esc_html($item->title) . '</a>';
            if ($has) {
                $output .= '<button class="ut-item__toggle" aria-label="Раскрыть">'
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

<header class="ut-site-header">
    <div class="ut-site-header__island">

        <div class="ut-site-header__logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="ut-site-header__name">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        </div>

        <div class="ut-site-header__nav">
            <?php wp_nav_menu([
                'theme_location'  => 'header-menu',
                'container'       => 'nav',
                'container_class' => 'ut-side-nav',
                'menu_class'      => 'ut-nav__list',
                'walker'          => new Hierarchical_Walker(),
                'depth'           => 2,
                'fallback_cb'     => false,
            ]); ?>
        </div>

        <button class="ut-toggle" aria-label="Открыть меню" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor"
                 stroke-width="2" fill="none" stroke-linecap="round">
                <line x1="3" y1="6"  x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

    </div>
</header>

<div class="ut-panel" aria-hidden="true">
    <div class="ut-panel__head">
        <span class="ut-panel__brand"><?php bloginfo('name'); ?></span>
        <button class="ut-close" aria-label="Закрыть меню">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor"
                 stroke-width="2" fill="none" stroke-linecap="round">
                <line x1="18" y1="6"  x2="6"  y2="18"></line>
                <line x1="6"  y1="6"  x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <nav class="ut-panel__body">
        <?php wp_nav_menu([
            'theme_location' => 'header-menu',
            'container'      => false,
            'menu_class'     => 'ut-panel__list',
            'walker'         => new Hierarchical_Panel_Walker(),
            'depth'          => 3,
            'fallback_cb'    => false,
        ]); ?>
    </nav>
</div>

<div class="ut-overlay"></div>

<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var header   = document.querySelector('.ut-site-header');
        var toggle   = document.querySelector('.ut-toggle');
        var panel    = document.querySelector('.ut-panel');
        var overlay  = document.querySelector('.ut-overlay');
        var closeBtn = panel ? panel.querySelector('.ut-close') : null;
        var body     = document.body;

        function positionDropdowns() {
            var items = header ? header.querySelectorAll('.ut-nav__list > .ut-nav__item') : [];
            items.forEach(function (item) {
                var sub = item.querySelector(':scope > .ut-item__sub');
                if (!sub) return;

                item.classList.remove('ut-sub-flip');
                if (item.getBoundingClientRect().left + sub.offsetWidth > window.innerWidth) {
                    item.classList.add('ut-sub-flip');
                }

                sub.querySelectorAll(':scope > .ut-nav__item').forEach(function (subItem) {
                    var subSub = subItem.querySelector(':scope > .ut-item__sub-sub');
                    if (!subSub) return;
                    subItem.classList.remove('ut-sub-sub-flip');
                    if (subItem.getBoundingClientRect().right + subSub.offsetWidth > window.innerWidth) {
                        subItem.classList.add('ut-sub-sub-flip');
                    }
                });
            });
        }

        function updateLayout() {
            if (!header) return;
            var island = header.querySelector('.ut-site-header__island');
            if (!island) return;
            header.classList.add('ut-is-desktop');
            void island.offsetWidth;
            var menuList = island.querySelector('.ut-nav__list');
            var fits = !menuList
                || menuList.getBoundingClientRect().right <= island.getBoundingClientRect().right + 1;
            header.classList.toggle('ut-is-desktop', fits);
            if (fits) {
                closePanel();
                positionDropdowns();
            }
        }

        function openPanel() {
            body.classList.add('ut-menu-open');
            panel.classList.add('ut-is-active');
            panel.setAttribute('aria-hidden', 'false');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }

        function closePanel() {
            body.classList.remove('ut-menu-open');
            panel.classList.remove('ut-is-active');
            panel.setAttribute('aria-hidden', 'true');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }

        if (toggle)   toggle.addEventListener('click', openPanel);
        if (closeBtn) closeBtn.addEventListener('click', closePanel);
        if (overlay)  overlay.addEventListener('click', closePanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && body.classList.contains('ut-menu-open')) closePanel();
        });

        if (panel) {
            panel.addEventListener('click', function (e) {
                var btn = e.target.closest('.ut-item__toggle');
                if (!btn) return;
                var li  = btn.closest('.ut-panel__item');
                if (!li) return;
                var sub = li.querySelector(':scope > .ut-panel__sub, :scope > .ut-panel__sub-sub');
                if (!sub) return;
                var isOpen = btn.classList.contains('ut-is-open');
                btn.classList.toggle('ut-is-open', !isOpen);
                sub.classList.toggle('ut-is-open', !isOpen);
                btn.setAttribute('aria-expanded', String(!isOpen));
            });
        }

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(updateLayout).observe(document.documentElement);
        } else {
            var resizeTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateLayout, 120);
            });
        }

        updateLayout();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(updateLayout);
        }
    });
})();
</script>
