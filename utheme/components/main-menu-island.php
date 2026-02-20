<header id="island-wrapper">
    <div class="island-main">
        <div class="island-logo">
            <?php the_custom_logo(); ?>
        </div>

        <div class="island-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
        </div>

        <div class="island-trigger">
            <button id="menu-toggle">
                <span></span>
                <div class="icon-dots"><span></span><span></span><span></span></div>
            </button>
        </div>
    </div>

    <div class="island-dropdown">
        <?php wp_nav_menu([
            "theme_location" => "header-menu",
            "container" => "nav",
            "container_class" => "island-nav",
            "menu_class" => "island-grid",
            "walker" => new Island_Walker() // Кастомный класс для картинок 
        ]); ?>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.getElementById('island-wrapper');
        const btn = document.getElementById('menu-toggle');

        btn.addEventListener('click', function(e) {
            e.preventDefault();

            wrapper.classList.toggle('is-open');

            const btnText = btn.querySelector('span');
            if (wrapper.classList.contains('is-open')) {
                btnText.textContent = '✖';
                btnText.textContent = '';
            } else {
                btnText.textContent = '';
            }
        });

        const links = document.querySelectorAll('.island-grid a');
        links.forEach(link => {
            link.addEventListener('click', () => {
                wrapper.classList.remove('is-open');
            });
        });
    });
</script>