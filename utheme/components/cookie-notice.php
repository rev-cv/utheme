<?php

/**
 * Cookie Notification Component
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
            <div class="cookie-modal" id="cookie-modal-window">
                <p><?php echo function_exists('get_site_translation') ? get_site_translation('cookie_notice_modal') : 'We use cookies to improve your experience.'; ?></p>
                
                <div class="cookie-buttons">
                    <button class="btn-accept" id="cookie-accept"><?php echo function_exists('get_site_translation') ? get_site_translation('accept_all') : 'Accept All'; ?></button>
                    <button class="btn-reject" id="cookie-reject"><?php echo function_exists('get_site_translation') ? get_site_translation('reject_all') : 'Reject All'; ?></button>
                </div>
                
                <div class="cookie-links">
                    <a href="/privacy-policy/"><?php echo function_exists('get_site_translation') ? get_site_translation('privacy_policy') : 'Privacy Policy'; ?></a>
                    <a href="/cookie-policy/"><?php echo function_exists('get_site_translation') ? get_site_translation('cookie_policy') : 'Privacy Policy'; ?></a>
                </div>
            </div>
        `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            const acceptBtn = document.getElementById('cookie-accept');
            const rejectBtn = document.getElementById('cookie-reject');

            if (acceptBtn) {
                acceptBtn.addEventListener('click', function() {
                    localStorage.setItem(storageKey, 'accepted');
                    removeModal();
                });
            }

            if (rejectBtn) {
                rejectBtn.addEventListener('click', function() {
                    localStorage.setItem(storageKey, 'rejected');
                    removeModal();
                });
            }
        }

        function removeModal() {
            const modal = document.getElementById('cookie-modal-window');
            if (modal) {
                modal.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                modal.style.opacity = '0';
                modal.style.transform = 'translate(-50%, 20px)';
                setTimeout(() => modal.remove(), 300);
            }
        }
    });
</script>