<header class="ut-island">
    <div class="ut-island__bar">
        <div class="ut-site-header__logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="ut-site-header__name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
        </div>

        <div class="ut-island__trigger">
            <button class="ut-toggle" aria-label="Toggle Menu" aria-expanded="false">
                <span></span>
                <span class="ut-island__dots"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>

    <div class="ut-island__dropdown">
        <?php wp_nav_menu([
            "theme_location"        => "header-menu",
            "container"             => "nav",
            "container_class"       => "ut-island__nav",
            "container_aria_label"  => "Main menu",
            "menu_class"            => "ut-island__grid",
            "walker"                => new Island_Walker(),
        ]); ?>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.querySelector('.ut-island');
        const btn = document.querySelector('.ut-toggle');

        function setOpen(open) {
            wrapper.classList.toggle('ut-is-open', open);
            btn.setAttribute('aria-expanded', String(open));
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            setOpen(!wrapper.classList.contains('ut-is-open'));
        });

        const links = document.querySelectorAll('.ut-island__grid a');
        links.forEach(link => {
            link.addEventListener('click', () => setOpen(false));
        });

        document.addEventListener('click', function(e) {
            if (wrapper.classList.contains('ut-is-open') && !wrapper.contains(e.target)) {
                setOpen(false);
            }
        });
    });
</script>
