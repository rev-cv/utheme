<?php
/**
 * Main Menu: Dynamic Style
 *
 * A menu with a large logo that shrinks on scroll,
 * transitioning from a vertical to a horizontal layout.
 */

// Get pages for menu items
$home_url = home_url('/');
$about_page = get_page_by_path('about-us');
$articles_page = get_page_by_path('articles');

$menu_items = [
    'home' => [
        'url' => $home_url,
        'title' => get_site_translation('home', 'Home'),
    ],
    'about' => [
        'url' => $about_page ? get_permalink($about_page) : home_url('/about-us/'),
        'title' => $about_page ? get_the_title($about_page->ID) : 'About Us',
    ],
    'articles' => [
        'url' => $articles_page ? get_permalink($articles_page) : home_url('/articles/'),
        'title' => $articles_page ? get_the_title($articles_page->ID) : 'Articles',
    ],
];

?>

<header id="dynamic-header">
    <div class="dynamic-header-wrapper">
        <div class="logo-container">
            <a href="<?php echo esc_url($home_url); ?>">
                <?php
                if (has_custom_logo()) {
                    $custom_logo_id = get_theme_mod('custom_logo');
                    $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                    if ($logo) {
                        echo '<img src="' . esc_url($logo[0]) . '" alt="' . get_bloginfo('name') . '">';
                    }
                } else {
                    echo '<span class="text-logo">' . get_bloginfo('name') . '</span>';
                }
                ?>
            </a>
        </div>

        <div class="menus-container">
            <!-- Vertical Menu (Initial State) -->
            <nav class="menu-vertical">
                <ul>
                    <?php foreach ($menu_items as $item) : ?>
                        <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- Horizontal Menu (Scrolled State) -->
            <nav class="menu-horizontal">
                <ul>
                    <?php foreach ($menu_items as $item) : ?>
                        <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>

        <div class="mobile-only-action">
            <a href="<?php echo esc_url($menu_items['articles']['url']); ?>" class="btn-articles">
                <?php echo esc_html($menu_items['articles']['title']); ?>
            </a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const header = document.getElementById('dynamic-header');
    if (!header) return;

    const scrollThreshold = 50; // Pixels to scroll before changing state

    const handleScroll = () => {
        if (window.scrollY > scrollThreshold) {
            header.classList.add('is-scrolled');
        } else {
            header.classList.remove('is-scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});
</script>