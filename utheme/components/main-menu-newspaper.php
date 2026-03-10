<?php
/**
 * Main Menu: Newspaper Style
 *
 * This menu uses `position: sticky` for the navigation bar,
 * which is a modern CSS-only approach that avoids JavaScript.
 */
?>
<header class="newspaper-brand">
    <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
</header>
<nav class="newspaper-nav">
    <?php $menu_slugs = ['articles', 'about-us']; ?>
    <ul class="newspaper-menu-list">
        <li><a href="<?php echo esc_url(home_url('/')); ?>">
            <?php echo get_site_translation('home'); ?>
        </a></li>
        <?php foreach ($menu_slugs as $slug): ?>
            <?php $page = get_page_by_path($slug); ?>
            <?php if ($page): ?>
                <li><a href="<?php echo esc_url(get_permalink($page->ID)); ?>">
                    <?php echo esc_html(get_the_title($page->ID)); ?>
                </a></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</nav>