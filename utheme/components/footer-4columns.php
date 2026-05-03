<?php
$show_site_name = (my_theme_get_config('is-menu-title', 'false') === 'true');
$compact_class  = $show_site_name ? '' : ' footer-4col--compact-brand';
?>
<div class="footer-4col-content">
    <div class="footer-4col-columns<?= $compact_class ?>">

        <!-- Column 1: Branding -->
        <div class="footer-4col-column footer-4col-brand">
            <?php if (function_exists('the_custom_logo') && has_custom_logo()): ?>
                <div class="footer-4col-logo">
                    <?php the_custom_logo(); ?>
                </div>
            <?php endif; ?>
            <?php if ($show_site_name): ?>
                <div class="footer-4col-site-name">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Column 2: Responsible Gaming -->
        <div class="footer-4col-column">
            <div class="footer-4col-column__title"><?php echo get_site_translation('responsible_gaming_title'); ?></div>
            <p><?php echo get_site_translation('legal_disclaimer'); ?></p>
            <p><?php echo get_responsible_gaming_block(); ?></p>
        </div>

        <!-- Column 3: Legal -->
        <div class="footer-4col-column">
            <div class="footer-4col-column__title"><?php echo get_site_translation('disclaimer'); ?></div>
            <p><?php echo get_site_translation('affiliate_disclosure'); ?></p>
            <p><?php echo get_site_translation('cookie_notice'); ?></p>
        </div>

        <!-- Column 4: Technical pages -->
        <div class="footer-4col-column">
            <div class="footer-4col-column__title"><?php echo get_site_translation('pages'); ?></div>
            <?php
            wp_nav_menu(array(
                'theme_location'  => 'footer-menu',
                'container'       => 'nav',
                'container_class' => 'footer-4col-nav',
                'menu_class'      => 'footer-4col-menu',
                'depth'           => 1,
            ));
            ?>
        </div>

    </div>
    <div class="footer-4col-copyright">
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></p>
    </div>
</div>
