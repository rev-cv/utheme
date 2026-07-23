<?php
if (!defined('ABSPATH')) exit;

const UTHEME_COOKIE_CONSENT_COOKIE = 'cookie_consent_status';

function utheme_cookie_consent_given(): bool {
    return !empty($_COOKIE[UTHEME_COOKIE_CONSENT_COOKIE]);
}

function utheme_cookie_push_should_render(): bool {
    if (my_theme_get_config('cookie-notice', 'original') !== 'push-banner') return false;
    return !utheme_cookie_consent_given();
}

function utheme_render_cookie_push_banner(): void {
    if (!utheme_cookie_push_should_render()) return;
    ?>
    <div class="ut-cookie ut-cookie--push" id="ut-cookie-push">
        <p class="ut-cookie__text"><?php echo function_exists('get_site_translation') ? get_site_translation('cookie_notice_modal') : 'We use cookies to improve your experience.'; ?></p>

        <div class="ut-cookie__actions">
            <button class="ut-cookie__btn ut-cookie__btn--accept"><?php echo function_exists('get_site_translation') ? get_site_translation('accept_all') : 'Accept All'; ?></button>
            <button class="ut-cookie__btn ut-cookie__btn--reject"><?php echo function_exists('get_site_translation') ? get_site_translation('reject_all') : 'Reject All'; ?></button>
        </div>

        <div class="ut-cookie__links">
            <a class="ut-cookie__link" href="/privacy-policy/"><?php echo function_exists('get_site_translation') ? get_site_translation('privacy_policy') : 'Privacy Policy'; ?></a>
            <a class="ut-cookie__link" href="/cookie-policy/"><?php echo function_exists('get_site_translation') ? get_site_translation('cookie_policy') : 'Cookie Policy'; ?></a>
        </div>
    </div>
    <script>
    (function () {
        var banner = document.getElementById('ut-cookie-push');
        if (!banner) return;
        var h = banner.offsetHeight;
        document.body.style.paddingTop = h + 'px';

        var header = document.querySelector('header');
        if (header) {
            var baseTop = parseFloat(getComputedStyle(header).top) || 0;
            header.style.top = (baseTop + h) + 'px';
            header.setAttribute('data-ut-cookie-base-top', baseTop);
        }
    })();
    </script>
    <?php
}
