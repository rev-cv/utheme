<header id="island-wrapper">
    <div class="island-main">
        <div class="island-logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="island-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
        </div>

        <div class="island-trigger">
            <button id="menu-toggle" aria-label="Toggle Menu" aria-expanded="false" aria-controls="island-dropdown">
                <span></span>
                <span class="icon-dots"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>

    <div id="island-dropdown" class="island-dropdown">
        <?php wp_nav_menu([
            "theme_location"        => "header-menu",
            "container"             => "nav",
            "container_class"       => "island-nav",
            "container_aria_label"  => "Main menu",
            "menu_class"            => "island-grid",
            "walker"                => new Island_Walker(),
        ]); ?>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.getElementById('island-wrapper');
        const btn = document.getElementById('menu-toggle');

        function setOpen(open) {
            wrapper.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', String(open));
            btn.querySelector('span').textContent = '';
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            setOpen(!wrapper.classList.contains('is-open'));
        });

        const links = document.querySelectorAll('.island-grid a');
        links.forEach(link => {
            link.addEventListener('click', () => setOpen(false));
        });

        document.addEventListener('click', function(e) {
            if (wrapper.classList.contains('is-open') && !wrapper.contains(e.target)) {
                setOpen(false);
            }
        });
    });
</script>