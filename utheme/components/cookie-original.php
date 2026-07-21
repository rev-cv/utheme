<?php

/**
 * Cookie Notification Component — Original
 *
 * Logic:
 * 1. Checks localStorage for consent on page load.
 * 2. If not found, injects the modal HTML into the DOM.
 * 3. Handles Accept/Reject actions using localStorage.
 */
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const storageKey = 'cookie_consent_status';

        if (!localStorage.getItem(storageKey)) {
            showCookieModal();
        }

        function showCookieModal() {
            const modalHTML = `
            <div class="ut-cookie ut-cookie--original">
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

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            const modal     = document.querySelector('.ut-cookie--original');
            const acceptBtn = modal.querySelector('.ut-cookie__btn--accept');
            const rejectBtn = modal.querySelector('.ut-cookie__btn--reject');

            acceptBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'accepted');
                removeModal(modal);
            });

            rejectBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'rejected');
                removeModal(modal);
            });
        }

        function removeModal(modal) {
            modal.addEventListener('transitionend', function onEnd() {
                modal.removeEventListener('transitionend', onEnd);
                modal.remove();
            });
            modal.classList.add('ut-is-closed');
        }
    });
</script>
