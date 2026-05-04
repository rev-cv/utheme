<?php

/**
 * Read the $more-pages variable from conf.scss (cached per request).
 */
function get_more_pages_default_style(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $scss = get_template_directory() . '/src/conf.scss';
    if (file_exists($scss) && preg_match('/\$more-pages:\s*"([^"]+)"/', file_get_contents($scss), $m)) {
        $cached = $m[1];
    } else {
        $cached = 'grid';
    }
    return $cached;
}

/**
 * Render the "more pages" component.
 *
 * @param int    $count Maximum number of pages (always displayed as odd).
 * @param string $style Variant: grid | list | slider | carousel. Empty = read from conf.scss.
 */
function render_more_pages(int $count = 5, string $style = ''): string {
    if (!is_singular()) return '';

    // Resolve style
    $allowed = ['grid', 'list', 'slider', 'carousel'];
    if (!$style || !in_array($style, $allowed, true)) {
        $style = get_more_pages_default_style();
    }
    if (!in_array($style, $allowed, true)) {
        $style = 'grid';
    }

    // Pre-query: clamp count to odd so we never over-fetch
    if ($count % 2 === 0) {
        $count--;
    }
    if ($count < 1) return '';

    $post_id   = get_the_ID();
    $parent_id = wp_get_post_parent_id($post_id);

    $tax_query = [];
    $utility   = get_term_by('name', 'Utility Pages', 'category');
    if ($utility) {
        $tax_query = [[
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => [$utility->term_id],
            'operator' => 'NOT IN',
        ]];
    }

    $args = [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'post__not_in'   => [$post_id],
        'orderby'        => 'rand',
        'tax_query'      => $tax_query,
    ];

    if ($parent_id) {
        $args['post_parent'] = $parent_id;
    }

    $query     = new WP_Query($args);
    $all_posts = $query->posts;

    // Post-query: enforce odd count on actual results
    $actual = count($all_posts);
    if ($actual % 2 === 0) {
        $actual--;
    }
    $all_posts = array_slice($all_posts, 0, $actual);

    if (empty($all_posts)) return '';

    // Enqueue interactive JS for carousel and slider
    if (in_array($style, ['carousel', 'slider'], true)) {
        add_action('wp_footer', '_more_pages_interactive_js', 99);
    }

    ob_start(); ?>
    <section class="more-pages more-pages--<?php echo esc_attr($style); ?>"
             aria-label="<?php esc_attr_e('More to read', 'utheme'); ?>">
        <div class="more-pages__items">
            <?php foreach ($all_posts as $pg):
                setup_postdata($GLOBALS['post'] = $pg);
                $pid   = $pg->ID;
                $url   = get_permalink($pid);
                $title = get_the_title($pid);

                $desc = trim((string) get_post_meta($pid, '_custom_seo_desc', true));
                if ($desc && function_exists('replace_placeholders_safely')) {
                    $desc = replace_placeholders_safely($desc);
                }
                if (!$desc) {
                    $desc = get_the_excerpt();
                }
                $desc = $desc ? wp_trim_words($desc, 15, '…') : '';

                $img_url   = get_the_post_thumbnail_url($pid, 'large');
                $has_thumb = $img_url ? ' has-thumb' : '';
            ?>
                <div class="more-pages__card<?php echo esc_attr($has_thumb); ?>">
                    <a class="more-pages__link"
                       href="<?php echo esc_url($url); ?>"
                       aria-label="<?php echo esc_attr($title); ?>"></a>
                    <?php if ($img_url) : ?>
                        <div class="more-pages__thumb">
                            <img src="<?php echo esc_url($img_url); ?>"
                                 alt=""
                                 loading="lazy">
                        </div>
                    <?php endif; ?>
                    <div class="more-pages__info">
                        <div class="more-pages__card-title"><?php echo esc_html($title); ?></div>
                        <?php if ($desc) : ?>
                            <p class="more-pages__desc"><?php echo esc_html($desc); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach;
            wp_reset_postdata(); ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode('more_pages', function (array $atts): string {
    $atts = shortcode_atts(['count' => 5, 'style' => ''], $atts, 'more_pages');
    return render_more_pages(max(1, intval($atts['count'])), (string) $atts['style']);
});

/**
 * Inline JS for carousel + slider — drag and direction-based auto-scroll.
 * Outputs once via wp_footer.
 */
function _more_pages_interactive_js(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
    <script>
    (function () {
        'use strict';

        var INTERVAL = 4000;

        function initTrack(section, track) {
            if (!track || track.children.length <= 1) return;

            var isDragging = false;
            var startX     = 0;
            var startScroll = 0;
            var autoTimer  = null;
            var direction  = 1; // 1 = forward, -1 = backward

            function cardStep() {
                var card = track.querySelector('.more-pages__card');
                if (!card) return track.clientWidth;
                var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
                return card.offsetWidth + gap;
            }

            function hasOverflow() {
                return track.scrollWidth > track.clientWidth + 1;
            }

            function advance() {
                if (!hasOverflow()) return;
                var max     = track.scrollWidth - track.clientWidth;
                var current = track.scrollLeft;
                var step    = cardStep();

                // Flip direction at boundaries
                if (direction === 1 && current >= max - 2) {
                    direction = -1;
                } else if (direction === -1 && current <= 2) {
                    direction = 1;
                }

                var next = Math.max(0, Math.min(current + direction * step, max));
                track.scrollTo({ left: next, behavior: 'smooth' });
            }

            function startAuto() {
                stopAuto();
                if (!hasOverflow()) return;
                autoTimer = setInterval(advance, INTERVAL);
            }

            function stopAuto() {
                if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
            }

            // Pause on hover
            section.addEventListener('mouseenter', stopAuto);
            section.addEventListener('mouseleave', startAuto);

            // Mouse drag
            track.addEventListener('mousedown', function (e) {
                isDragging  = true;
                startX      = e.pageX - track.getBoundingClientRect().left;
                startScroll = track.scrollLeft;
                track.style.cursor = 'grabbing';
                stopAuto();
            });

            document.addEventListener('mousemove', function (e) {
                if (!isDragging) return;
                e.preventDefault();
                var x = e.pageX - track.getBoundingClientRect().left;
                track.scrollLeft = startScroll - (x - startX);
            });

            document.addEventListener('mouseup', function () {
                if (!isDragging) return;
                isDragging = false;
                track.style.cursor = '';
                // Update direction based on where user dragged to
                direction = track.scrollLeft >= (track.scrollWidth - track.clientWidth) / 2 ? -1 : 1;
                startAuto();
            });

            // Touch
            track.addEventListener('touchstart', function (e) {
                startX      = e.touches[0].pageX;
                startScroll = track.scrollLeft;
                stopAuto();
            }, { passive: true });

            track.addEventListener('touchend', function () {
                direction = track.scrollLeft >= (track.scrollWidth - track.clientWidth) / 2 ? -1 : 1;
                startAuto();
            }, { passive: true });

            startAuto();
        }

        document.querySelectorAll('.more-pages--carousel, .more-pages--slider').forEach(function (section) {
            var track = section.querySelector('.more-pages__items');
            initTrack(section, track);
        });
    })();
    </script>
    <?php
}
