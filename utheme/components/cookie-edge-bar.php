<?php

/**
 * Cookie Notification Component — Edge Bar
 *
 * Плашка снизу во всю ширину, вплотную к нижнему и боковым краям вьюпорта
 * (в отличие от Original — без центрирования, отступов и скругления).
 * Перекрывает часть контента, высоту страницы не раздвигает.
 */
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const storageKey = 'cookie_consent_status';

        if (!localStorage.getItem(storageKey)) {
            showCookieBar();
        }

        function showCookieBar() {
            const barHTML = `
            <div class="ut-cookie ut-cookie--edge">
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
        `;

            document.body.insertAdjacentHTML('beforeend', barHTML);

            const bar       = document.querySelector('.ut-cookie--edge');
            const acceptBtn = bar.querySelector('.ut-cookie__btn--accept');
            const rejectBtn = bar.querySelector('.ut-cookie__btn--reject');

            acceptBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'accepted');
                removeBar(bar);
            });

            rejectBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'rejected');
                removeBar(bar);
            });
        }

        function removeBar(bar) {
            bar.addEventListener('transitionend', function onEnd() {
                bar.removeEventListener('transitionend', onEnd);
                bar.remove();
            });
            bar.classList.add('ut-is-closed');
        }
    });
</script>
