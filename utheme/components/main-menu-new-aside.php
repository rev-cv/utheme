<header id="site-header">
    <div class="header-island container">
        <div class="site-logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="site-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
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
        <button class="menu-close" aria-label="Close">&times;</button>
        <div class="aside-title"><?php echo get_site_translation('related_articles'); ?></div>
    </div>

    <?php wp_nav_menu([
        "theme_location" => "header-menu",
        "container" => "nav",
        "container_class" => "side-nav",
        "menu_class" => "side-menu-list",
        "walker" => new Island_Walker(),
        "depth" => 1,
    ]); ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('.menu-toggle');
        const close = document.querySelector('.menu-close');
        const overlay = document.querySelector('.menu-overlay');
        const panel = document.querySelector('.side-panel');
        const header = document.querySelector('.header-island');
        const body = document.body;

        const getScrollbarWidth = () => {
            return window.innerWidth - document.documentElement.clientWidth;
        };

        function openMenu() {
            const scrollWidth = getScrollbarWidth();

            body.classList.add('menu-open');
            panel.classList.add('is-active');
            body.style.paddingRight = `${scrollWidth}px`;
        }

        function closeMenu() {
            panel.classList.remove('is-active');

            setTimeout(() => {
                body.classList.remove('menu-open');
                body.style.paddingRight = '';
                if (header) header.style.marginRight = '';
            }, 400);
        }

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            openMenu();
        });

        close.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
    });
</script>