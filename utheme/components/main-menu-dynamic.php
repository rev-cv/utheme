<?php
/**
 * Main Menu: Dynamic Style
 *
 * Expanded: thumbnails + grid (3 columns).
 * Scrolled:  thumbnails + horizontal row with overflow "···" button.
 */

$home_url = home_url( '/' );
?>

<header id="dynamic-header">
    <div class="dynamic-header-wrapper">

        <!-- Logo -->
        <div class="logo-container">
            <a href="<?php echo esc_url( $home_url ); ?>">
                <?php if ( has_custom_logo() ) :
                    $logo_id  = get_theme_mod( 'custom_logo' );
                    $logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
                    if ( $logo_src ) : ?>
                        <img src="<?php echo esc_url( $logo_src[0] ); ?>"
                             alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                    <?php endif;
                else : ?>
                    <span class="text-logo"><?php bloginfo( 'name' ); ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Menus -->
        <div class="menus-container">

            <!-- Vertical grid (initial state, scroll = 0) -->
            <?php wp_nav_menu( [
                'theme_location'  => 'header-menu',
                'container'       => 'nav',
                'container_class' => 'menu-vertical',
                'menu_class'      => 'dyn-menu',
                'walker'          => new Dynamic_Menu_Walker(),
                'depth'           => 1,
                'fallback_cb'     => false,
            ] ); ?>

            <!-- Horizontal row (scrolled state) + overflow button -->
            <div class="menu-horizontal-wrap">
                <?php wp_nav_menu( [
                    'theme_location'  => 'header-menu',
                    'container'       => 'nav',
                    'container_class' => 'menu-horizontal',
                    'menu_class'      => 'dyn-menu',
                    'walker'          => new Dynamic_Menu_Walker(),
                    'depth'           => 1,
                    'fallback_cb'     => false,
                ] ); ?>

                <div class="dyn-more-wrap" id="dyn-more-wrap" hidden>
                    <button class="dyn-more-btn" id="dyn-more-btn"
                            aria-haspopup="true" aria-expanded="false"
                            aria-label="<?php esc_attr_e( 'More menu items', 'utheme' ); ?>">
                        <span aria-hidden="true">•••</span>
                    </button>
                    <ul class="dyn-overflow-list" id="dyn-overflow-list" role="menu"></ul>
                </div>
            </div>
        </div>

    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const header   = document.getElementById('dynamic-header');
    if (!header) return;

    const moreWrap = document.getElementById('dyn-more-wrap');
    const moreBtn  = document.getElementById('dyn-more-btn');
    const overList = document.getElementById('dyn-overflow-list');
    const horizNav = header.querySelector('.menu-horizontal');

    const SCROLL_THRESHOLD = 50;
    const MOBILE_BP        = 1024; // px — same as the SCSS breakpoint

    let scrolledPast = window.scrollY > SCROLL_THRESHOLD;

    // True when the header must stay in compact/horizontal mode
    function shouldBeCompact() {
        return scrolledPast || window.innerWidth <= MOBILE_BP;
    }

    // ── Apply compact / expanded state ────────────────────────────────────
    function applyState(closing = false) {
        const compact = shouldBeCompact();
        const wasCompact = header.classList.contains('is-scrolled');

        header.classList.toggle('is-scrolled', compact);

        if (!compact && wasCompact) {
            // Returning to expanded state — reset horizontal menu items
            if (horizNav) {
                horizNav.querySelector('.dyn-menu')
                    ?.querySelectorAll(':scope > .dyn-item')
                    .forEach(li => li.style.display = '');
            }
            if (moreWrap) moreWrap.hidden = true;
            closeDropdown();
        } else if (compact) {
            updateOverflow();
        }
    }

    // ── Scroll ────────────────────────────────────────────────────────────
    window.addEventListener('scroll', () => {
        // Always close the dropdown while user is scrolling
        if (overList.classList.contains('is-open')) closeDropdown();

        const nowPast = window.scrollY > SCROLL_THRESHOLD;
        if (nowPast === scrolledPast) return; // no state change
        scrolledPast = nowPast;
        applyState();
    }, { passive: true });

    // ── Resize ────────────────────────────────────────────────────────────
    let resizeTimer;
    window.addEventListener('resize', () => {
        // Debounce slightly so we don't thrash on every pixel of resize
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            closeDropdown();
            applyState();
        }, 80);
    }, { passive: true });

    // ── Initial render ────────────────────────────────────────────────────
    applyState();

    // ── Overflow detection ────────────────────────────────────────────────
    function updateOverflow() {
        if (!horizNav || !moreWrap) return;

        const ul = horizNav.querySelector('.dyn-menu');
        if (!ul) return;

        const items = Array.from(ul.querySelectorAll(':scope > .dyn-item'));
        if (!items.length) return;

        // 1. Show all items, hide more button — get clean measurements
        items.forEach(li => li.style.display = '');
        moreWrap.hidden = true;
        ul.offsetHeight; // force reflow

        // 2. Measure
        const style    = getComputedStyle(ul);
        const gap      = parseFloat(style.columnGap) || parseFloat(style.gap) || 16;
        const navW     = horizNav.offsetWidth;
        const moreBtnW = 72; // reserved width for "···" button

        let totalW = 0;
        items.forEach((li, i) => { totalW += li.offsetWidth + (i > 0 ? gap : 0); });

        if (totalW <= navW) return; // everything fits, done

        // 3. Find the cutoff index
        const available = navW - moreBtnW;
        let cumW = 0, overflowIdx = items.length;

        for (let i = 0; i < items.length; i++) {
            cumW += items[i].offsetWidth + (i > 0 ? gap : 0);
            if (cumW > available) { overflowIdx = i; break; }
        }

        if (overflowIdx >= items.length) return;

        // 4. Hide overflowing items in the nav bar
        for (let i = overflowIdx; i < items.length; i++) {
            items[i].style.display = 'none';
        }

        // 5. Clone them into the dropdown
        overList.innerHTML = '';
        for (let i = overflowIdx; i < items.length; i++) {
            const clone = items[i].cloneNode(true);
            clone.style.display = '';
            overList.appendChild(clone);
        }

        moreWrap.hidden = false;
    }

    // ── Dropdown open / close ─────────────────────────────────────────────
    function openDropdown() {
        overList.classList.add('is-open');
        moreBtn.setAttribute('aria-expanded', 'true');
    }
    function closeDropdown() {
        if (!overList || !moreBtn) return;
        overList.classList.remove('is-open');
        moreBtn.setAttribute('aria-expanded', 'false');
    }

    moreBtn.addEventListener('click', e => {
        e.stopPropagation();
        overList.classList.contains('is-open') ? closeDropdown() : openDropdown();
    });

    document.addEventListener('click',   e => { if (!moreWrap?.contains(e.target)) closeDropdown(); });
    document.addEventListener('focusin', e => { if (!moreWrap?.contains(e.target)) closeDropdown(); });
});
</script>
