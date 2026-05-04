<header id="site-header">
    <div class="header-island container">
        <div class="site-logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="site-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo("name"); ?></a>
        </div>

        <button class="menu-toggle" aria-label="Open Menu">
            <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
</header>

<div class="menu-overlay"></div>

<div class="side-panel">
    <div class="side-panel-header">
        <button class="drill-back" aria-label="Back" hidden>
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span class="drill-title"></span>
        </button>
        <button class="menu-close" aria-label="Close">&times;</button>
    </div>

    <div class="drill-viewport">
        <?php wp_nav_menu([
            "theme_location" => "header-menu",
            "container"       => "nav",
            "container_class" => "side-nav",
            "menu_class"      => "side-menu-list",
            "walker"          => new Aside_Walker(),
            "depth"           => 3,
        ]); ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    var CORNER_SVG = '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle       = document.querySelector('.menu-toggle');
        var close        = document.querySelector('.menu-close');
        var overlay      = document.querySelector('.menu-overlay');
        var panel        = document.querySelector('.side-panel');
        var headerIsland = document.querySelector('.header-island');
        var body         = document.body;
        var backBtn      = panel.querySelector('.drill-back');
        var titleEl      = backBtn.querySelector('.drill-title');
        var viewport     = panel.querySelector('.drill-viewport');
        var nav          = viewport.querySelector('.side-nav');

        // ── Открытие / закрытие ───────────────────────────────────────────────

        function openMenu() {
            menuIsOpen = true;
            body.classList.add('menu-open');
            viewport.style.overflowY = 'hidden';

            // Measure natural height (capped at 80vh)
            panel.style.height = 'auto';
            var maxH = Math.round(window.innerHeight * 0.8);
            var target = Math.min(panel.scrollHeight, maxH);
            panel.style.height = '0';

            // Force reflow then animate
            panel.offsetHeight; // eslint-disable-line no-unused-expressions
            panel.style.transition = 'height 0.35s ease';
            panel.style.height = target + 'px';

            function onOpen() {
                panel.removeEventListener('transitionend', onOpen);
                panel.style.transition = '';
                viewport.style.overflowY = '';
            }
            panel.addEventListener('transitionend', onOpen);
        }

        function closeMenu() {
            menuIsOpen = false;
            viewport.style.overflowY = 'hidden';

            // Pin current height, then animate to 0
            panel.style.height = panel.offsetHeight + 'px';
            panel.offsetHeight; // eslint-disable-line no-unused-expressions
            panel.style.transition = 'height 0.35s ease';
            panel.style.height = '0';

            function onClose() {
                panel.removeEventListener('transitionend', onClose);
                body.classList.remove('menu-open');
                viewport.style.overflowY = '';
                resetDrill();
            }
            panel.addEventListener('transitionend', onClose);
        }

        var menuIsOpen = false;

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            menuIsOpen ? closeMenu() : openMenu();
        });
        if (close)   close.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);

        // ── Drill-down ────────────────────────────────────────────────────────

        var stack          = [];
        var counter        = 0;
        var _heightHandler = null;

        function init() {
            var rootList = nav.querySelector('.side-menu-list');
            if (!rootList) return;

            rootList.dataset.drillId = 'root';

            // Уровень 0 → уровень 1
            rootList.querySelectorAll(':scope > li.has-submenu').forEach(function (li) {
                var subMenu = li.querySelector('.sub-menu-list');
                if (!subMenu) return;

                var id    = 'dp' + (++counter);
                var title = (li.querySelector('.menu-title') || {}).textContent || '';
                var url   = (li.querySelector('a') || {}).href || '';

                li.dataset.drillTarget     = id;
                subMenu.dataset.drillId    = id;
                subMenu.dataset.drillTitle = title.trim();
                subMenu.dataset.drillUrl   = url;

                // Уровень 1 → уровень 2
                subMenu.querySelectorAll(':scope > li').forEach(function (subLi) {
                    var subSubMenu = subLi.querySelector('.sub-sub-menu-list');
                    if (!subSubMenu) return;

                    var subId    = 'dp' + (++counter);
                    var subAnchor = subLi.querySelector('a') || {};
                    var subTitle = (subAnchor.textContent || '').trim();
                    var subUrl   = subAnchor.href || '';

                    subLi.classList.add('has-submenu');
                    subLi.dataset.drillTarget     = subId;
                    subSubMenu.dataset.drillId    = subId;
                    subSubMenu.dataset.drillTitle = subTitle;
                    subSubMenu.dataset.drillUrl   = subUrl;

                    // Инжектируем кнопку-уголок для уровня 1
                    var corner = document.createElement('button');
                    corner.className = 'drill-corner';
                    corner.setAttribute('aria-label', 'Open submenu');
                    corner.innerHTML = CORNER_SVG;
                    subLi.appendChild(corner);

                    nav.appendChild(subSubMenu);
                });

                nav.appendChild(subMenu);
            });

            setPanel(rootList, 'drill-panel--active');
            rootList.classList.add('drill-panel');

            nav.querySelectorAll('[data-drill-id]:not([data-drill-id="root"])').forEach(function (p) {
                p.classList.add('drill-panel', 'drill-panel--next');
            });

            stack = [{ el: rootList, title: '', url: '' }];
        }

        function setPanel(el, cls) {
            el.classList.remove('drill-panel--active', 'drill-panel--prev', 'drill-panel--next');
            if (cls) el.classList.add(cls);
        }

        function drillIn(targetId) {
            panel.classList.remove('is-going-back');
            var targetPanel = nav.querySelector('[data-drill-id="' + targetId + '"]');
            if (!targetPanel) return;

            setPanel(stack[stack.length - 1].el, 'drill-panel--prev');
            setPanel(targetPanel, 'drill-panel--active');

            stack.push({
                el:    targetPanel,
                title: targetPanel.dataset.drillTitle || '',
                url:   targetPanel.dataset.drillUrl   || '',
            });

            updateHeader();
            recalcHeight();
        }

        function drillOut() {
            if (stack.length <= 1) return;
            panel.classList.add('is-going-back');
            var cur = stack.pop();
            setPanel(cur.el, 'drill-panel--next');
            setPanel(stack[stack.length - 1].el, 'drill-panel--active');
            updateHeader();
            recalcHeight();
        }

        function resetDrill() {
            panel.classList.remove('is-going-back');
            nav.querySelectorAll('.drill-panel').forEach(function (p) {
                setPanel(p, null);
                p.classList.add('drill-panel--next');
            });
            if (stack[0]) setPanel(stack[0].el, 'drill-panel--active');
            stack = stack.slice(0, 1);
            updateHeader();
        }

        function updateHeader() {
            if (stack.length > 1) {
                backBtn.removeAttribute('hidden');
                titleEl.textContent = stack[stack.length - 1].title;
            } else {
                backBtn.setAttribute('hidden', '');
                titleEl.textContent = '';
            }
        }

        var _recalcTimer = null;

        function recalcHeight() {
            if (_heightHandler) {
                panel.removeEventListener('transitionend', _heightHandler);
                _heightHandler = null;
            }
            if (_recalcTimer) {
                clearTimeout(_recalcTimer);
            }
            _recalcTimer = setTimeout(function () {
                _recalcTimer = null;
                var active = nav.querySelector('.drill-panel--active');
                if (!active) return;
                void active.offsetHeight;
                var panelHeader = panel.querySelector('.side-panel-header');
                var headerH = panelHeader ? panelHeader.offsetHeight : 0;
                var maxH    = Math.round(window.innerHeight * 0.8);
                var target  = Math.min(headerH + active.scrollHeight, maxH);
                panel.style.transition = 'height 0.25s ease';
                panel.style.height = target + 'px';
                _heightHandler = function (e) {
                    if (e.propertyName !== 'height') return;
                    panel.removeEventListener('transitionend', _heightHandler);
                    _heightHandler = null;
                    panel.style.transition = '';
                };
                panel.addEventListener('transitionend', _heightHandler);
            }, 200);
        }

        init();

        if (backBtn) backBtn.addEventListener('click', drillOut);

        // Клик по уголку — drill-in; клик по основной части карточки — переход
        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('.submenu-toggle, .drill-corner');
            if (!btn) return;
            var trigger = btn.closest('[data-drill-target]');
            if (!trigger) return;
            e.preventDefault();
            drillIn(trigger.dataset.drillTarget);
        });
    });
})();
</script>
