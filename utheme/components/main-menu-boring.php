<header class="ut-site-header">
    <div class="ut-site-header__island container">
        <div class="ut-site-header__logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="ut-site-header__name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo("name"); ?></a>
        </div>

        <button class="ut-toggle" aria-label="Open Menu">
            <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
</header>

<div class="ut-overlay"></div>

<div class="ut-panel">
    <div class="ut-panel__head">
        <button class="ut-drill__back" aria-label="Back" hidden>
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span class="ut-drill__title"></span>
        </button>
        <button class="ut-close" aria-label="Close">&times;</button>
    </div>

    <div class="ut-drill__viewport">
        <?php wp_nav_menu([
            "theme_location" => "header-menu",
            "container"       => "nav",
            "container_class" => "ut-side-nav",
            "menu_class"      => "ut-drill__root",
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
        var toggle       = document.querySelector('.ut-toggle');
        var close        = document.querySelector('.ut-close');
        var overlay      = document.querySelector('.ut-overlay');
        var panel        = document.querySelector('.ut-panel');
        var headerIsland = document.querySelector('.ut-site-header__island');
        var body         = document.body;
        var backBtn      = panel.querySelector('.ut-drill__back');
        var titleEl      = backBtn.querySelector('.ut-drill__title');
        var viewport     = panel.querySelector('.ut-drill__viewport');
        var nav          = viewport.querySelector('.ut-side-nav');

        // ── Открытие / закрытие ───────────────────────────────────────────────

        function openMenu() {
            menuIsOpen = true;
            body.classList.add('ut-menu-open');
            viewport.style.overflowY = 'hidden';

            panel.style.height = 'auto';
            var maxH = Math.round(window.innerHeight * 0.8);
            var target = Math.min(panel.scrollHeight, maxH);
            panel.style.height = '0';

            panel.offsetHeight;
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

            panel.style.height = panel.offsetHeight + 'px';
            panel.offsetHeight;
            panel.style.transition = 'height 0.35s ease';
            panel.style.height = '0';

            function onClose() {
                panel.removeEventListener('transitionend', onClose);
                body.classList.remove('ut-menu-open');
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
            var rootList = nav.querySelector('.ut-drill__root');
            if (!rootList) return;

            rootList.dataset.drillId = 'root';

            rootList.querySelectorAll(':scope > li.ut-item--has-sub').forEach(function (li) {
                var subMenu = li.querySelector('.ut-item__sub');
                if (!subMenu) return;

                var id    = 'dp' + (++counter);
                var title = (li.querySelector('.ut-item__label') || {}).textContent || '';
                var url   = (li.querySelector('a') || {}).href || '';

                li.dataset.drillTarget     = id;
                subMenu.dataset.drillId    = id;
                subMenu.dataset.drillTitle = title.trim();
                subMenu.dataset.drillUrl   = url;

                subMenu.querySelectorAll(':scope > li').forEach(function (subLi) {
                    var subSubMenu = subLi.querySelector('.ut-item__sub-sub');
                    if (!subSubMenu) return;

                    var subId    = 'dp' + (++counter);
                    var subAnchor = subLi.querySelector('a') || {};
                    var subTitle = (subAnchor.textContent || '').trim();
                    var subUrl   = subAnchor.href || '';

                    subLi.classList.add('ut-item--has-sub');
                    subLi.dataset.drillTarget     = subId;
                    subSubMenu.dataset.drillId    = subId;
                    subSubMenu.dataset.drillTitle = subTitle;
                    subSubMenu.dataset.drillUrl   = subUrl;

                    var corner = document.createElement('button');
                    corner.className = 'ut-drill__corner';
                    corner.setAttribute('aria-label', 'Open submenu');
                    corner.innerHTML = CORNER_SVG;
                    subLi.appendChild(corner);

                    nav.appendChild(subSubMenu);
                });

                nav.appendChild(subMenu);
            });

            setPanel(rootList, 'ut-drill__panel--active');
            rootList.classList.add('ut-drill__panel');

            nav.querySelectorAll('[data-drill-id]:not([data-drill-id="root"])').forEach(function (p) {
                p.classList.add('ut-drill__panel', 'ut-drill__panel--next');
            });

            stack = [{ el: rootList, title: '', url: '' }];
        }

        function setPanel(el, cls) {
            el.classList.remove('ut-drill__panel--active', 'ut-drill__panel--prev', 'ut-drill__panel--next');
            if (cls) el.classList.add(cls);
        }

        function drillIn(targetId) {
            panel.classList.remove('ut-is-going-back');
            var targetPanel = nav.querySelector('[data-drill-id="' + targetId + '"]');
            if (!targetPanel) return;

            setPanel(stack[stack.length - 1].el, 'ut-drill__panel--prev');
            setPanel(targetPanel, 'ut-drill__panel--active');

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
            panel.classList.add('ut-is-going-back');
            var cur = stack.pop();
            setPanel(cur.el, 'ut-drill__panel--next');
            setPanel(stack[stack.length - 1].el, 'ut-drill__panel--active');
            updateHeader();
            recalcHeight();
        }

        function resetDrill() {
            panel.classList.remove('ut-is-going-back');
            nav.querySelectorAll('.ut-drill__panel').forEach(function (p) {
                setPanel(p, null);
                p.classList.add('ut-drill__panel--next');
            });
            if (stack[0]) setPanel(stack[0].el, 'ut-drill__panel--active');
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
                var active = nav.querySelector('.ut-drill__panel--active');
                if (!active) return;
                void active.offsetHeight;
                var panelHeader = panel.querySelector('.ut-panel__head');
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

        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('.ut-item__toggle, .ut-drill__corner');
            if (!btn) return;
            var trigger = btn.closest('[data-drill-target]');
            if (!trigger) return;
            e.preventDefault();
            drillIn(trigger.dataset.drillTarget);
        });
    });
})();
</script>
