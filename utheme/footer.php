<?php get_template_part('components/cookie-notice') ?>
<footer>
    <div class="container">
        <?php
            $menu_type = my_theme_get_config('footer-menu', '2columns');

            if ($menu_type === '2columns') {
                get_template_part('components/footer-2columns');
            } elseif ($menu_type === 'marquee') {
                get_template_part('components/main-menu-marquee');
            } elseif ($menu_type === 'central') {
                get_template_part('components/footer-central');
            } else {
                get_template_part('components/footer-2columns');
            }
        ?>
    </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>