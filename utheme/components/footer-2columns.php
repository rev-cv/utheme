<div class="footer-content">
    <div class="footer-columns">
        <div class="footer-column footer-column-left">
            <div class="footer-column__title"><?php echo get_site_translation('information'); ?></div>
            <?php
            wp_nav_menu(array(
                'theme_location' => 'footer-menu',
                'container'      => 'nav',
                'container_class' => 'footer-nav',
                'menu_class'     => 'footer-menu',
                'depth'          => 1,
            ));
            ?>
        </div>
        <div class="footer-column footer-column-right">
            <div class="footer-column__title"><?php echo get_site_translation('disclaimer'); ?></div>
            <p><?php echo get_site_translation('legal_disclaimer'); ?></p>
            <p><?php echo get_site_translation('cookie_notice'); ?></p>
            <p><?php echo get_site_translation('affiliate_disclosure'); ?></p>
            <p><?php echo get_responsible_gaming_block(); ?></p>
        </div>
    </div>
    <div class="footer-copyright">
        <p><?php echo date('F Y'); ?> | © <?php bloginfo('name'); ?></p>
    </div>
</div>