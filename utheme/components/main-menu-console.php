<?php
/**
 * Main Menu: Console Style
 * 
 * Ultra-compact menu (30px height).
 */

$about_page = get_page_by_path('about-us');
$articles_page = get_page_by_path('articles');

$about_url = $about_page ? get_permalink($about_page) : home_url('/about-us/');
$articles_url = $articles_page ? get_permalink($articles_page) : home_url('/articles/');

$about_text = $about_page ? get_the_title($about_page->ID) : 'About Us';
$articles_text = $articles_page ? get_the_title($articles_page->ID) : 'Articles';

// SVG Icons defined in variables
$icon_about = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
$icon_articles = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

?>

<header id="console-header">
    <div class="console-wrapper">
        <div class="console-left">
            <div class="console-icon">
                <?php 
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '"><img src="' . esc_url(get_site_icon_url(32)) . '" alt="Site Icon"></a>';
                }
                ?>
            </div>
            <div class="console-title">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
            </div>
        </div>

        <nav class="console-right">
            <a href="<?php echo esc_url($about_url); ?>" class="console-link" aria-label="<?php echo esc_attr($about_text); ?>">
                <span class="link-text"><?php echo esc_html($about_text); ?></span>
                <span class="link-icon">
                    <?php echo $icon_about; ?>
                </span>
            </a>
            <a href="<?php echo esc_url($articles_url); ?>" class="console-link" aria-label="<?php echo esc_attr($articles_text); ?>">
                <span class="link-text"><?php echo esc_html($articles_text); ?></span>
                <span class="link-icon">
                    <?php echo $icon_articles; ?>
                </span>
            </a>
        </nav>
    </div>
</header>