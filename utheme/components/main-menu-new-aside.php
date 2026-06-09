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
        <button class="ut-close" aria-label="Close">&times;</button>
        <div class="ut-panel__title"><?php echo get_site_translation(
            "related_articles",
        ); ?></div>
    </div>

    <?php wp_nav_menu([
        "theme_location" => "header-menu",
        "container" => "nav",
        "container_class" => "ut-side-nav",
        "menu_class" => "ut-panel__list",
        "walker" => new Aside_Walker(),
        "depth" => 3,
    ]); ?>
</div>

<script>
    document.querySelectorAll('.ut-item:not(.ut-item--has-sub)').forEach((item, i) => {
        item.style.setProperty('--item-delay', `${((i + 2) * 0.05).toFixed(2)}s`);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const isBoringMenu = <?php echo json_encode(
            my_theme_get_config("main-menu", "island") === "boring",
        ); ?>;

        const toggle  = document.querySelector('.ut-toggle');
        const close   = document.querySelector('.ut-close');
        const overlay = document.querySelector('.ut-overlay');
        const panel   = document.querySelector('.ut-panel');
        const header  = document.querySelector('.ut-site-header__island');
        const body    = document.body;

        const getScrollbarWidth = () =>
            window.innerWidth - document.documentElement.clientWidth;

        function closeAllSubmenus() {
            panel.querySelectorAll('.ut-item__toggle[aria-expanded="true"]').forEach(btn => {
                btn.setAttribute('aria-expanded', 'false');
                btn.closest('.ut-item--has-sub').querySelector('.ut-item__sub').classList.remove('ut-is-open');
            });
        }

        function openMenu() {
            const scrollWidth = getScrollbarWidth();
            body.classList.add('ut-menu-open');
            panel.classList.add('ut-is-active');
            body.style.paddingRight = `${scrollWidth}px`;
        }

        function closeMenu() {
            panel.classList.remove('ut-is-active');
            setTimeout(() => {
                body.classList.remove('ut-menu-open');
                closeAllSubmenus();
                if (!isBoringMenu) {
                    body.style.paddingRight = '';
                    if (header) header.style.marginRight = '';
                }
            }, 400);
        }

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            panel.classList.contains('ut-is-active') ? closeMenu() : openMenu();
        });

        close.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);

        // Submenu toggles
        panel.addEventListener('click', (e) => {
            const btn = e.target.closest('.ut-item__toggle');
            if (!btn) return;

            e.preventDefault();
            const card    = btn.closest('.ut-item--has-sub');
            const submenu = card.querySelector('.ut-item__sub');
            const isOpen  = btn.getAttribute('aria-expanded') === 'true';

            // Close all other open submenus
            closeAllSubmenus();

            if (!isOpen) {
                btn.setAttribute('aria-expanded', 'true');
                submenu.classList.add('ut-is-open');
            }
        });
    });
</script>
