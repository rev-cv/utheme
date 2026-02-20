<?php get_header() ?>

<main class="main-404">
    <div class="container error-404-content">
        <h1 class="error-code">404</h1>
        <p><?php echo get_site_translation('404_title'); ?></p>
        <p><?php echo get_site_translation('404_description'); ?></p>

        <div class="error-btns">
            <a href="<?php echo home_url('/'); ?>"><?php echo get_site_translation('404_back_home'); ?></a>
            <a href="<?php echo home_url('/articles/'); ?>"><?php echo get_site_translation('404_go_to_kb'); ?></a>
        </div>
    </div>
</main>

<?php

get_footer();
