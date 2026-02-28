<?php
// Ищем родительскую страницу 'articles' для получения дочерних элементов.
$parent_page_path = 'articles';
$parent_page = get_page_by_path($parent_page_path);
$articles = [];
$parent_page_link = home_url('/' . $parent_page_path . '/');

if ($parent_page) {
    $parent_page_link = get_permalink($parent_page->ID);
    $args = [
        'post_type'      => 'page', // Предполагаем, что статьи - это страницы (pages)
        'posts_per_page' => -1,
        'post_parent'    => $parent_page->ID,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ];
    $articles_query = new WP_Query($args);
    if ($articles_query->have_posts()) {
        $articles = $articles_query->get_posts();
    }
    wp_reset_postdata(); // Важно после кастомного WP_Query
}
?>

<button class="docs-menu-toggle-btn" aria-label="Переключить меню" aria-expanded="true">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 11c0-3.771 0-5.657 1.172-6.828S6.229 3 10 3h4c3.771 0 5.657 0 6.828 1.172S22 7.229 22 11v2c0 3.771 0 5.657-1.172 6.828S17.771 21 14 21h-4c-3.771 0-5.657 0-6.828-1.172S2 16.771 2 13z"/><path stroke-linecap="round" d="M5.5 10h6m-5 4h4"/><path stroke-linecap="round" d="M15 21V3" opacity="0.5"/></g></svg>
</button>

<!-- Боковая панель в стиле документации -->
<aside class="docs-menu-sidebar">
    <div class="docs-menu-header">
        <div class="site-logo">
            <?php the_custom_logo(); ?>
        </div>
        <div class="site-name">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
        </div>
    </div>

    <div class="docs-menu-content">
        <ul class="docs-menu-list">
            <?php if (!empty($articles)) : ?>
                <?php $current_page_id = get_the_ID(); ?>
                <?php foreach ($articles as $article) : ?>
                    <li class="docs-menu-item">
                        <a href="<?php echo esc_url(get_permalink($article->ID)); ?>"<?php if ($current_page_id === $article->ID) echo ' class="active"'; ?>>
                            <?php if (has_post_thumbnail($article->ID)) : ?>
                                <?php echo get_the_post_thumbnail($article->ID, 'thumbnail', ['class' => 'docs-menu-item-img']); ?>
                            <?php else : ?>
                                <!-- Изображение-заглушка -->
                                <img src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/placeholder.png" alt="" class="docs-menu-item-img">
                            <?php endif; ?>
                            <span class="docs-menu-item-title"><?php echo esc_html(get_the_title($article->ID)); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="docs-menu-item-empty"><?php echo get_site_translation('no_articles_found'); // Предполагается наличие функции перевода ?></li>
            <?php endif; ?>
        </ul>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('.docs-menu-sidebar');
    const toggleBtn = document.querySelector('.docs-menu-toggle-btn');
    const body = document.body;

    if (!panel || !toggleBtn) return;

    const DESKTOP_BREAKPOINT = 992;
    const PANEL_WIDTH = 280; // from docs.scss
    let wasDesktop = window.innerWidth >= DESKTOP_BREAKPOINT;

    // A single state variable for menu open/closed status
    let isMenuOpen = wasDesktop;

    const isDesktop = () => window.innerWidth >= DESKTOP_BREAKPOINT;
    const getScrollbarWidth = () => window.innerWidth - document.documentElement.clientWidth;

    const updateDOM = (open) => {
        toggleBtn.setAttribute('aria-expanded', String(open));
        if (open) {
            panel.classList.add('is-active');
            panel.classList.remove('is-closed');
            body.classList.remove('docs-menu-closed');
            if (!isDesktop()) {
                const scrollWidth = getScrollbarWidth();
                body.style.paddingRight = `${scrollWidth}px`;
                body.classList.add('docs-menu-open');
            }
        } else {
            panel.classList.remove('is-active');
            panel.classList.add('is-closed');
            body.classList.add('docs-menu-closed');
            if (!isDesktop()) {
                const onTransitionEnd = () => {
                    body.classList.remove('docs-menu-open');
                    body.style.paddingRight = '';
                    panel.removeEventListener('transitionend', onTransitionEnd);
                };
                panel.addEventListener('transitionend', onTransitionEnd);
            }
        }
    };

    const setMenuState = (open) => {
        if (isMenuOpen === open) {
            // If state is already correct, just ensure DOM matches (for cases like snapping back)
            updateDOM(open);
            return;
        }
        isMenuOpen = open;
        updateDOM(open);
    };

    // --- Event Listeners ---
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        setMenuState(!isMenuOpen);
    });

    window.addEventListener('resize', () => {
        const isNowDesktop = isDesktop();
        if (wasDesktop && !isNowDesktop) { // Desktop -> Mobile
            if (isMenuOpen) {
                setMenuState(false);
            }
        } else if (!wasDesktop && isNowDesktop) { // Mobile -> Desktop
            if (!isMenuOpen) {
                setMenuState(true);
            }
        }
        wasDesktop = isNowDesktop;
    });

    // --- Swipe Logic ---
    let touchStartX = 0;
    let touchStartY = 0;
    let touchCurrentX = 0;
    let isSwiping = false;
    const SWIPE_THRESHOLD = 30;
    const SWIPE_COMPLETE_THRESHOLD = 80;

    const handleTouchStart = (e) => {
        if (isDesktop()) return;
        const SWIPE_OPEN_ZONE = window.innerWidth * 0.1;
        if (isMenuOpen || e.touches[0].clientX <= SWIPE_OPEN_ZONE) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchCurrentX = touchStartX;
            isSwiping = false;
        } else {
            touchStartX = 0;
        }
    };

    const handleTouchMove = (e) => {
        if (isDesktop() || touchStartX === 0) return;
        touchCurrentX = e.touches[0].clientX;
        const deltaX = touchCurrentX - touchStartX;
        const deltaY = e.touches[0].clientY - touchStartY;

        if (!isSwiping && Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > SWIPE_THRESHOLD) {
            isSwiping = true;
            panel.style.transition = 'none';
        }

        if (isSwiping) {
            if (!isMenuOpen && deltaX > 0) { // Opening
                const translateX = Math.min(0, -PANEL_WIDTH + deltaX);
                panel.style.transform = `translateX(${translateX}px)`;
            } else if (isMenuOpen && deltaX < 0) { // Closing
                const translateX = Math.max(-PANEL_WIDTH, deltaX);
                panel.style.transform = `translateX(${translateX}px)`;
            }
        }
    };

    const handleTouchEnd = () => {
        if (isDesktop() || !isSwiping) {
            isSwiping = false;
            return;
        }
        panel.style.transition = '';
        panel.style.transform = '';
        const deltaX = touchCurrentX - touchStartX;
        if (deltaX > SWIPE_COMPLETE_THRESHOLD && !isMenuOpen) {
            setMenuState(true);
        } else if (deltaX < -SWIPE_COMPLETE_THRESHOLD && isMenuOpen) {
            setMenuState(false);
        } else {
            setMenuState(isMenuOpen); // Snap back
        }
        isSwiping = false;
    };

    document.addEventListener('touchstart', handleTouchStart, {
        passive: true
    });
    document.addEventListener('touchmove', handleTouchMove, {
        passive: true
    });
    document.addEventListener('touchend', handleTouchEnd, {
        passive: true
    });

    // --- Initial Setup ---
    panel.style.transition = 'none';
    updateDOM(isMenuOpen);
    requestAnimationFrame(() => {
        panel.style.transition = '';
    });
});
</script>