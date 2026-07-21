<?php

/**
 * Cookie Notification Component — Push Banner
 *
 * Плашка во всю ширину у самого верха страницы. При появлении/закрытии
 * плавно раздвигает/схлопывает содержимое страницы (padding-top на body)
 * и, если найдена, — сдвигает шапку текущего варианта main-menu
 * (.ut-site-header / .ut-island) через её собственный inline-стиль top.
 * Best-effort: main-menu: docs (постоянный левый сайдбар) не имеет верхнего
 * бара, поэтому сдвигается только контент — сайдбар остаётся на месте.
 * Вся логика — только в этом компоненте, файлы main-menu не редактируются.
 */
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const storageKey = 'cookie_consent_status';

        if (!localStorage.getItem(storageKey)) {
            showCookieBanner();
        }

        function showCookieBanner() {
            const bannerHTML = `
            <div class="ut-cookie ut-cookie--push">
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

            document.body.insertAdjacentHTML('afterbegin', bannerHTML);

            const banner    = document.querySelector('.ut-cookie--push');
            const acceptBtn = banner.querySelector('.ut-cookie__btn--accept');
            const rejectBtn = banner.querySelector('.ut-cookie__btn--reject');

            // Известные селекторы шапки текущего main-menu-варианта (best-effort,
            // без правки файлов main-menu — только чтение DOM по классам).
            const header = document.querySelector('.ut-site-header, .ut-island');
            const headerBaseTop = header ? (parseFloat(getComputedStyle(header).top) || 0) : 0;

            document.body.style.transition = 'padding-top 0.35s ease';
            if (header) {
                header.style.transition = 'top 0.35s ease';
            }

            let isOpen = false;
            let resizeTimer = null;

            // Пересчитывает высоту баннера (переносы текста меняются при ресайзе
            // окна) и синхронно обновляет padding-top body и top шапки.
            function applyPush() {
                const h = banner.scrollHeight;
                document.body.style.paddingTop = h + 'px';
                if (header) {
                    header.style.top = (headerBaseTop + h) + 'px';
                }
            }

            function onResize() {
                if (!isOpen) return;
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(applyPush, 100);
            }

            requestAnimationFrame(function() {
                isOpen = true;
                banner.style.transform = 'translateY(0)';
                applyPush();
            });

            window.addEventListener('resize', onResize);

            acceptBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'accepted');
                closeBanner();
            });

            rejectBtn.addEventListener('click', function() {
                localStorage.setItem(storageKey, 'rejected');
                closeBanner();
            });

            function closeBanner() {
                isOpen = false;
                window.removeEventListener('resize', onResize);
                clearTimeout(resizeTimer);

                banner.style.transform = 'translateY(-100%)';
                document.body.style.paddingTop = '0px';
                if (header) {
                    header.style.top = headerBaseTop + 'px';
                }

                banner.addEventListener('transitionend', function onEnd(e) {
                    if (e.target !== banner || e.propertyName !== 'transform') return;
                    banner.removeEventListener('transitionend', onEnd);
                    banner.remove();
                    document.body.style.paddingTop = '';
                    document.body.style.transition = '';
                    if (header) {
                        header.style.removeProperty('top');
                        header.style.removeProperty('transition');
                    }
                });
            }
        }
    });
</script>
