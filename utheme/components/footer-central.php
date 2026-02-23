<?php
/**
 * Footer Component: Central Layout
 *
 * A stacked layout focusing on a central alignment.
 */
?>
<div class="footer-central-content">

    <div class="footer-branding">
        <div class="footer-logo">
            <?php
            // Display the custom logo if it exists
            if (function_exists('the_custom_logo') && has_custom_logo()) {
                the_custom_logo();
            }
            ?>
        </div>
        <div class="footer-site-name">
            <a href="<?php echo home_url('/'); ?>"><?php bloginfo('name'); ?></a>
        </div>
    </div>

    <?php
    // Display the footer navigation menu if it's assigned
    if (has_nav_menu('footer-menu')) {
        wp_nav_menu(array(
            'theme_location' => 'footer-menu',
            'container'      => 'nav',
            'container_class' => 'footer-central-nav',
            'menu_class'     => 'footer-central-menu',
            'depth'          => 1,
        ));
    }
    ?>

    <div class="footer-disclaimers">
        <p><?php echo get_site_translation('legal_disclaimer'); ?></p>
        <p><?php echo get_site_translation('affiliate_disclosure'); ?></p>
        <p><?php echo get_responsible_gaming_block(); ?></p>
        <p><?php echo get_site_translation('cookie_notice'); ?></p>
    </div>

    <div class="footer-copyright">
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></p>
    </div>

</div>